<?php

namespace MengBao\MEBPwm\form;

use pocketmine\player\Player;

use jojoe77777\FormAPI\CustomForm;
use MengBao\MEBPwm\Main;
use MengBao\MEBPwm\manager\OfflineRatingManager;

class RatingForm
{
    /**
     * 显示评分窗口（支持双向评分）
     * @param Player $player 评分人
     * @param string $targetPlayer 被评分人name
     * @param string $orderId 订单ID
     * @param string $type 评分类型：employer_rate_player/player_rate_employer
     * @param string $taskId 离线任务ID（可选）
     */
    public static function show(Player $player, string $targetPlayer, string $orderId, string $type, string $taskId = ""): void
    {
        $lang = Main::getInstance()->getLanguageManager();
        $ratingManager = Main::getInstance()->getRatingManager();
        $offlineRatingManager = Main::getInstance()->getOfflineRatingManager();
        // 表单标题（区分双向评分）
        $title = match ($type) {
            "employer_rate_player" => $lang->get("rating_employer_title", ["player" => $targetPlayer]),
            "player_rate_employer" => $lang->get("rating_companion_title", ["player" => $targetPlayer]),
            default => $lang->get("rating_default_title")
        };
        $form = new CustomForm(function (Player $player, $data) use ($lang, $ratingManager, $offlineRatingManager, $targetPlayer, $orderId, $taskId) {
            if ($data === null) {
                $player->sendMessage($lang->get("rating_canceled"));
                // 离线任务：取消后标记为过期
                if (!empty($taskId)) {
                    $offlineRatingManager->updateTaskStatus($taskId, OfflineRatingManager::STATUS_EXPIRED);
                }
                return;
            }
            // 解析评分数据
            $score = (float) $data[0] + 1.0; // 下拉框索引转评分
            $comment = trim($data[1]);
            if ($score < 1 || $score > 5) {
                $player->sendMessage($lang->get("rating_score_invalid"));
                return;
            }
            // 保存评分记录
            $ratingManager->addRating(
                $targetPlayer, // 被评分人
                $player->getName(), // 评分人
                $score,
                $comment
            );
            // 离线任务：标记为完成
            if (!empty($taskId)) {
                $offlineRatingManager->updateTaskStatus($taskId, OfflineRatingManager::STATUS_COMPLETED);
            }
            $player->sendMessage($lang->get("rating_success", ["target" => $targetPlayer, "score" => $score, "avg" => $ratingManager->getAvgRating($targetPlayer)]));
        });

        // 表单配置
        $form->setTitle($title);
        $form->addDropdown($lang->get("score"), ["1", "2", "3", "4", "5"], 4);
        $form->addInput($lang->get("comment"), $lang->get("rating_comment_placeholder"), $lang->get("no_comment"));
        $player->sendForm($form);
    }
}
