<?php

namespace MengBao\MEBPwm\command;

use pocketmine\command\{Command, CommandExecutor, CommandSender, CommandMap};
use pocketmine\player\Player;
use MengBao\MEBPwm\Main;
use MengBao\MEBPwm\form\{MainMenuForm, PublishForm, RecruitListForm, RankForm};
// use cooldogedev\BedrockEconomy\api\BedrockEconomyAPI;
use MengBao\MEBsociety\Units\Economy;

class PwmCommand extends Command implements CommandExecutor
{
    private Main $plugin;
    private $lang; // 语言快捷引用
    private $economy;

    public function __construct(Main $plugin)
    {
        parent::__construct(
            "pwm",
            "陪玩系统核心指令",
            "/pwm [publish|accept|tp|settle|rate|rank]",
            ["pw"]
        );
        $this->plugin = $plugin;
        $this->lang = $plugin->getLanguageManager(); // 语言管理器
        $this->setPermission("MEBPwm.ge");
        // 获取MEBSociety插件实例和经济实例
        $mebPlugin = $plugin->getServer()->getPluginManager()->getPlugin("MEBsociety");
        $this->economy = Economy::getInstance($mebPlugin);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        return $this->onCommand($sender, $this, $commandLabel, $args);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if (!$sender instanceof Player) {
            // 替换为多语言
            $sender->sendMessage($this->lang->get("only_player"));
            return true;
        }

        if (empty($args[0])) {
            MainMenuForm::send($sender);
            return true;
        }

        switch ($args[0]) {
            case "publish":
                PublishForm::open($sender);
                break;

            case "accept":
                if (empty($args[1])) {
                    $sender->sendMessage($this->lang->get("usage", ["usage" => "/pwm accept <招募ID> <申请玩家名>"]));
                    break;
                }
                $this->acceptRecruit($sender, $args[1], $args[2]);
                break;

            case "tp":
                if (empty($args[1])) {
                    $sender->sendMessage($this->lang->get("usage", ["usage" => "/pwm tp <玩家名>"]));
                    break;
                }
                $this->tpToTeammate($sender, $args[1]);
                break;

            case "settle":
                if (empty($args[1])) {
                    $sender->sendMessage($this->lang->get("usage", ["usage" => "/pwm settle <订单ID> <完成度(0-100)>"]));
                    break;
                }
                $completeRate = isset($args[2]) ? (int) $args[2] : 100;
                $this->settleOrder($sender, $args[1], $completeRate);
                break;

            case "rate":
                if (empty($args[1]) || empty($args[2])) {
                    $sender->sendMessage($this->lang->get("usage", ["usage" => "/pwm rate <玩家名> <评分(1.0-5.0)> <评论>"]));
                    break;
                }
                $score = (float) $args[2];
                if ($score < 1.0 || $score > 5.0) {
                    $sender->sendMessage($this->lang->get("rating_invalid"));
                    break;
                }
                $this->plugin->getRatingManager()->addRating($args[1], $sender->getName(), $score, $args[3] ?? null);
                $sender->sendMessage($this->lang->get("rating_success", [
                    "target" => $args[1],
                    "score" => $score,
                    "avg" => $this->plugin->getRatingManager()->getAvgRating($args[1])
                ]));
                break;

            case "rank":
                RankForm::show($sender);
                break;

            default:
                $sender->sendMessage($this->lang->get("unknown_command"));
        }

        return true;
    }

    /**
     * 接受招募申请
     */
    private function acceptRecruit(Player $employer, string $recruitId, string $playerName): void
    {
        $employerName = $employer->getName();
        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        $recruit = $this->plugin->getRecruitManager()->getRecruit($recruitId);
        if (!$recruit) {
            $employer->sendMessage($this->lang->get("recruit_not_exist"));
            return;
        }
        // 生成订单ID
        $orderId = uniqid("ord_");
        // 冻结报酬到临时账户
        $reward = $recruit["reward"];
        $this->plugin->getTempAccountManager()->setBalance($orderId, $reward);
        // 创建订单
        $this->plugin->getOrderManager()->createOrder(
            $orderId,
            $recruitId,
            $employerName,
            $playerName,
            $recruit["duration"] * 60,
            $reward
        );
        // 更新招募状态
        $this->plugin->getRecruitManager()->updateRecruitStatus($recruitId, "closed");
        // 创建组队
        $this->plugin->getTeamManager()->createTeam($employerName, $playerName);
        $employer->sendMessage($this->lang->get("recruit_accepted", [
            "player" => $playerName,
            "id" => $orderId
        ]));
        $player?->sendMessage($this->lang->get("accept_recruit_success", [
            "id" => $orderId
        ]));
    }

    /**
     * 传送至队友
     */
    private function tpToTeammate(Player $player, string $targetName): void
    {
        $target = $this->plugin->getServer()->getPlayerExact($targetName);
        if (!$target instanceof Player) {
            $player->sendMessage($this->lang->get("target_offline"));
            return;
        }

        if (!$this->plugin->getTeamManager()->isTeammate($player->getName(), $targetName)) {
            $player->sendMessage($this->lang->get("not_teammate", ["target" => $targetName]));
            return;
        }

        if (!$this->plugin->getTeamManager()->checkTpCooldown($player)) {
            return;
        }

        $player->teleport($target->getPosition());
        $player->sendMessage($this->lang->get("tp_success", ["target" => $targetName]));
    }

    /**
     * 结算订单
     */
    private function settleOrder(Player $player, string $orderId, int $completeRate): void
    {
        $order = $this->plugin->getOrderManager()->getOrder($orderId);
        if (!$order) {
            $player->sendMessage($this->lang->get("order_not_exist"));
            return;
        }
        if ($order["employer"] !== $player->getName()) {
            $player->sendMessage($this->lang->get("not_order_employer"));
            return;
        }
        if ($order["status"] !== "running") {
            $player->sendMessage($this->lang->get("order_already_settled"));
            return;
        }

        // 计算报酬和退款
        $totalReward = $this->plugin->getTempAccountManager()->getBalance($orderId);
        $actualReward = $totalReward * ($completeRate / 100);
        $refund = $totalReward - $actualReward;

        // 转账
        if ($actualReward > 0) {
            $targetPlayer = $order["player"];
            $this->economy->addMoney($targetPlayer, $actualReward);
        }
        if ($refund > 0) {
            $playerName = $player->getName();
            $this->economy->addMoney($playerName, $refund);
        }

        // 更新订单状态
        $this->plugin->getOrderManager()->completeOrder($orderId, $completeRate, $player, $targetPlayer);

        // 记录陪玩时长
        $playTime = $order["duration"] - $order["remaining_time"];
        $this->plugin->getPlayTimeManager()->addPlayTime($order["player"], $playTime);

        // 清空临时账户
        $this->plugin->getTempAccountManager()->removeBalance($orderId);

        $player->sendMessage($this->lang->get("order_settle_success", [
            "rate" => $completeRate,
            "pay" => $actualReward,
            "refund" => $refund
        ]));
        $companion = $this->plugin->getServer()->getPlayerExact($order["player"]);
        if ($companion instanceof Player) {
            $companion->sendMessage($this->lang->get("order_companion_tip", [
                "pay" => $actualReward,
                "rate" => $completeRate
            ]));
        }
    }
}