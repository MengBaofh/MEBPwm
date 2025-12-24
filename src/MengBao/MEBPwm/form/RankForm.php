<?php

namespace MengBao\MEBPwm\form;

use pocketmine\player\Player;

use jojoe77777\FormAPI\SimpleForm;
use MengBao\MEBPwm\Main;
use MengBao\MEBPwm\manager\RatingManager;

class RankForm
{
    public static function show(Player $player): void
    {
        $lang = Main::getInstance()->getLanguageManager();
        $ratingManager = Main::getInstance()->getRatingManager();
        $playTimeManager = Main::getInstance()->getPlayTimeManager();
        $playTimes = $playTimeManager->getAllPlayTimes();
        if (empty($playTimes)) {
            $player->sendMessage($lang->get("no_play_time"));
            return;
        }

        // 排序
        arsort($playTimes);

        $form = new SimpleForm(function (Player $player, $data) use ($lang, $ratingManager, $playTimes) {
            // 点击空白/关闭表单时返回
            if ($data === null || !isset($playTimes[array_keys($playTimes)[$data]])) {
                return;
            }
            // 获取点击的目标玩家名
            $targetPlayer = array_keys($playTimes)[$data];
            // 打开该玩家的评分详情页
            self::showRatingDetail($player, $targetPlayer, $lang, $ratingManager);
        });
        // 多语言标题
        $form->setTitle($lang->get("rank_title"));

        $rank = 1;
        foreach ($playTimes as $name => $seconds) {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $timeText = $lang->get("total_time") . $hours . $lang->get("hours") . $minutes . $lang->get("minutes");
            $avgRating = $ratingManager->getAvgRating($name); // 获取平均分
            $ratingText = $lang->get("score") . $avgRating . "/5.0";
            $buttonText = "§aNo.{$rank}: {$name} §a({$ratingText}§a)\n§b{$timeText}";
            $form->addButton($buttonText, 0, "textures/ui/star");
            $rank++;
            if ($rank > 10)
                break; // 仅显示前10名
        }
        $player->sendForm($form);
    }
    /**
     * 评分详情页：显示平均分 + 所有评分/评论
     */
    private static function showRatingDetail(Player $player, string $targetPlayer, $lang, RatingManager $ratingManager): void
    {
        $avgRating = $ratingManager->getAvgRating($targetPlayer);
        $allRatings = $ratingManager->getAllRatingsForPlayer($targetPlayer);
        $content = "";
        // 1. 拼接平均分
        $content .= $lang->get("score") . $avgRating . "\n\n";
        // 2. 拼接所有评分记录
        if (empty($allRatings)) {
            $content .= "§c" . $lang->get("no_rating_records");
        } else {
            foreach ($allRatings as $rating) {
                $rater = $rating["rater"] ?? "未知玩家";
                $score = $rating["score"] ?? 0;
                $comment = empty($rating["comment"]) ? $lang->get("no_comment") : $rating["comment"];

                $recordText = $lang->get("rater") . $rater . "\n";
                $recordText .= $lang->get("score") . $score . "\n";
                $recordText .= $lang->get("comment") . $comment . "\n\n";

                $content .= $recordText; // 拼接单条评分记录
            }
        }

        $detailForm = new SimpleForm(function (Player $player, $data) {
            // 点击按钮关闭/返回（SimpleForm的data是按钮索引）
            if ($data === 1) { // 索引1是返回排行榜，索引0是关闭
                self::show($player);
            }
        });
        $detailForm->setTitle($lang->get("rating_detail_title", ["player" => $targetPlayer]));
        // 添加按钮
        $detailForm->addButton($lang->get("btn_close"), 0, "textures/ui/close");
        $detailForm->addButton($lang->get("btn_back"), 0, "textures/ui/arrow_left"); // 新增返回排行榜按钮
        $detailForm->setContent($content);
        $player->sendForm($detailForm);
    }
}