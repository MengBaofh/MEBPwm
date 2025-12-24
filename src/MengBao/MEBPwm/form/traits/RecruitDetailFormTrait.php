<?php
namespace MengBao\MEBPwm\form\traits;

use pocketmine\player\Player;
use jojoe77777\FormAPI\SimpleForm;
use MengBao\MEBPwm\Main;

/**
 * 招募详情表单复用 Trait
 */
trait RecruitDetailFormTrait
{
    /**
     * 展示招募详情表单
     * @param Player $player 玩家对象
     * @param string $recruitId 招募完整ID
     * @param bool $isShortId 是否为精简ID（可选，兼容告示牌的精简ID场景）
     */
    public static function showRecruitDetailForm(Player $player, string $recruitId, bool $isShortId = false): void
    {
        $plugin = Main::getInstance();
        $lang = $plugin->getLanguageManager();
        $recruitManager = $plugin->getRecruitManager();

        // 兼容精简ID（适配告示牌场景的短ID）
        $realRecruitId = $isShortId ? $recruitManager->getFullIdByShortId($recruitId) : $recruitId;
        if ($isShortId && !$realRecruitId) {
            $player->sendMessage($lang->get("recruit_invalid_or_expired"));
            return;
        }

        // 校验招募有效性
        $recruit = $recruitManager->getRecruit($realRecruitId);
        if (!$recruit || $recruit["status"] !== "pending") {
            $player->sendMessage($lang->get("recruit_invalid_or_expired"));
            return;
        }
        $employerName = $recruit["employer"];
        $employer = $plugin->getServer()->getPlayerExact($employerName);
        // 构建详情表单
        $form = new SimpleForm(function (Player $player, $data) use ($employerName, $realRecruitId) {
            // 点击取消/关闭按钮
            if ($data === null || $data !== 0) {
                return;
            }
            // 执行接受招募逻辑
            // self::executeAcceptRecruitCommand($player, $realRecruitId);
            // 处理玩家发送的陪玩申请
            self::handleApply($player, $employerName, $realRecruitId);
        });
        // 空值防护 + 组装详情内容
        $employerName = $employerName ?? "未知";
        $content = $recruit['content'] ?? "无";
        $duration = $recruit['duration'] ?? 0;
        $reward = $recruit['reward'] ?? 0;
        $createTime = $recruit['create_time'] ?? time();
        $rating = $plugin->getRatingManager()->getAvgRating($employerName);
        $createTimeStr = date("Y-m-d H:i:s", $createTime);
        $form->setTitle($lang->get("recruit_detail_title"));
        $form->setContent(
            "§a" . $lang->get("employer") . $employerName . " | " .
            "§9" . $lang->get("score") . $rating . "/5.0\n" .
            "§e" . $lang->get("duration") . $duration . $lang->get("minutes") . " | " .
            "§6" . $lang->get("reward") . $reward . $lang->get("money") . "\n" .
            "§b" . $lang->get("content") . $content . "\n" .
            "§5" . $lang->get("create_time") . $createTimeStr
        );
        $form->addButton($lang->get("btn_accept"), 0, "textures/ui/accept");
        $form->addButton($lang->get("btn_cancel"), 0, "textures/ui/cancel");
        $player->sendForm($form);
    }

    private static function handleApply(Player $player, string $employerName, string $recruitId): void
    {
        $plugin = Main::getInstance();
        $playerName = $player->getName();
        $recruit = $plugin->getRecruitManager()->getRecruit($recruitId);
        //检查是否是雇主本身
        if ($recruit["employer"] === $playerName) {
            $player->sendMessage($plugin->getLanguageManager()->get("cannot_accept_self_recruit"));
            return;
        }
        // 检查陪玩是否已有雇主
        if ($plugin->getOrderManager()->isPlayerEmployed($playerName)) {
            $player->sendMessage($plugin->getLanguageManager()->get("already_employed"));
            return;
        }
        // 检查是否已存在待处理申请
        if ($plugin->getApplyManager()->hasPendingApply($playerName, $employerName, $recruitId)) {
            $player->sendMessage($plugin->getLanguageManager()->get("duplicate_apply"));
            return;
        }
        // 创建申请
        $applyId = $plugin->getApplyManager()->createApply($playerName, $employerName, $recruitId);
        // 通知雇主（如果在线）
        $employer = $plugin->getServer()->getPlayerExact($employerName);
        if ($employer instanceof Player && $employer->isOnline()) {
            $employer->sendMessage($plugin->getLanguageManager()->get("new_apply_notify", [
                "player" => $playerName
            ]));
        }
        $player->sendMessage($plugin->getLanguageManager()->get("apply_sent"));
    }

    /**
     * 自定义秒数转时分秒（支持超过24小时）
     * @param int $seconds 总秒数
     * @return string 格式：HH:MM:SS（如 25:30:15）或 X小时X分X秒（如 25小时30分15秒）
     */
    public static function formatSeconds(int $seconds): string
    {
        // 计算小时（总秒数 ÷ 3600）
        $hours = (int) ($seconds / 3600);
        // 剩余秒数 = 总秒数 % 3600
        $remaining = $seconds % 3600;
        // 计算分钟（剩余秒数 ÷ 60）
        $minutes = (int) ($remaining / 60);
        // 计算最终秒数
        $secs = $remaining % 60;
        // 补零格式（如 25:05:08）
        return sprintf("%02d:%02d:%02d", $hours, $minutes, $secs);
    }

}
