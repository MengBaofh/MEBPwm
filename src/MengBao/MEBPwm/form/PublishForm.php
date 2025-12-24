<?php

namespace MengBao\MEBPwm\form;

use pocketmine\player\Player;
use MengBao\MEBPwm\listener\PlayerEventListener;

use jojoe77777\FormAPI\CustomForm;
use MengBao\MEBPwm\Main;
use MengBao\MEBPwm\economy\EconomyAdapter;

class PublishForm
{
    public static function open(Player $player): void
    {
        $lang = Main::getInstance()->getLanguageManager();
        // 获取MEBSociety插件实例和经济实例
        $mebPlugin = $player->getServer()->getPluginManager()->getPlugin("MEBsociety");
        if ($mebPlugin === null) {
            $player->sendMessage($lang->get("plugin_not_found", ["plugin" => "MEBsociety"]));
            return;
        }
        $economy = EconomyAdapter::getInstance();
        if (!$economy->isAvailable()) {
            $player->sendMessage($lang->get("no_economy_plugin"));
            return;
        }
        $form = new CustomForm(function (Player $player, $data) use ($lang, $economy) {
            if ($data === null)
                return;

            $content = trim($data[0]);
            $duration = (int) $data[1];
            $reward = (float) $data[2];

            // 验证输入（多语言提示）
            if (empty($content)) {
                $player->sendMessage($lang->get("recruit_content_empty"));
                return;
            }
            if ($duration < 5 || $duration > 1440) {
                $player->sendMessage($lang->get("recruit_duration_invalid"));
                return;
            }
            if ($reward < 0.1) {
                $player->sendMessage($lang->get("recruit_reward_invalid"));
                return;
            }

            // 检查余额
            $playerName = $player->getName();
            $balance = $economy->getMoney($playerName);
            if ($balance === (float) -1) {
                $player->sendMessage($lang->get("data_not_found"));
                return;
            }
            if ($balance < $reward) {
                $player->sendMessage($lang->get("balance_insufficient", [
                    "balance" => $balance,
                    "reward" => $reward
                ]));
                return;
            }
            // 冻结报酬到临时账户
            $subtractResult = $economy->addMoney($playerName, -$reward);
            if (!$subtractResult) {
                $player->sendMessage($lang->get("recruit_deduct_failed"));
                return;
            }
            // 扣款成功提示
            $player->sendMessage($lang->get("recruit_deduct_success", ["balance" => $balance])); // 扣款成功提示
            // 生成招募ID并保存
            $recruitId = uniqid("rec_");
            Main::getInstance()->getRecruitManager()->createRecruit(
                $recruitId,
                $player->getName(),
                $content,
                $duration,
                $reward
            );
            $shortRecruitId = substr($recruitId, -8);
            // 组装告示牌内容（最多4行）
            $signContent = [
                $lang->get("logo"), // 第一行
                $lang->get("recruitId") . $shortRecruitId, // 第二行
                $lang->get("content") . mb_substr($content, 0, 7),
                $duration . $lang->get("minutes") . "|" . $reward . $lang->get("money")
            ];
            // 暂存内容到监听器的静态数组中
            PlayerEventListener::setPendingSignContent($player, $signContent, $recruitId);

            // 多语言提示
            $player->sendMessage($lang->get("recruit_publish_success", ["id" => $recruitId]));
            $player->sendMessage($lang->get("generate_sign_tip"));
        });

        // 多语言表单标题和输入提示
        $form->setTitle($lang->get("publish_recruit_title"));
        $form->addInput($lang->get("content"), $lang->get("input_content"));
        $form->addInput($lang->get("duration") . "(" . $lang->get("minutes") . ")", $lang->get("input_duration"), "60");
        $form->addInput($lang->get("reward"), $lang->get("input_reward"), "10");
        $player->sendForm($form);
    }
}