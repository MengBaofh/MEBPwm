<?php

namespace MengBao\MEBPwm\form;

use pocketmine\player\Player;
use jojoe77777\FormAPI\SimpleForm;

use MengBao\MEBPwm\Main;
use MengBao\MEBPwm\form\traits\RecruitDetailFormTrait; // 引入Trait

class RecruitListForm
{
    use RecruitDetailFormTrait; // 复用Trait中的方法
    public static function show(Player $player): void
    {
        $lang = Main::getInstance()->getLanguageManager();
        $recruitManager = Main::getInstance()->getRecruitManager();
        $recruits = Main::getInstance()->getRecruitManager()->getAllPendingRecruits();
        if (empty($recruits)) {
            $player->sendMessage($lang->get("no_pending_recruit"));
            return;
        }
        $form = new SimpleForm(function (Player $player, $data) use ($recruitManager, $lang) {
            // 1. 基础校验：$data为空 → 返回
            if ($data === null || $data === "") {
                return;
            }
            // 2. 校验招募ID是否有效
            $selectedId = (string) $data; // 确保是字符串类型
            if (!$recruitManager->getRecruit($selectedId)) {
                $player->sendMessage($lang->get("recruit_invalid_or_expired"));
                return;
            }
            // 3. 调用详情表单
            self::showRecruitDetailForm($player, $selectedId);
        });

        // 多语言标题
        $form->setTitle($lang->get("recruit_list_title"));
        foreach ($recruits as $id => $info) {
            // 空值防护
            $employer = $info['employer'] ?? "未知";
            $reward = $info['reward'] ?? 0;
            $rating = Main::getInstance()->getRatingManager()->getAvgRating($employer) ?? 0;
            $text = "§a" . $employer . " | " . $lang->get("score") . $rating . "/5.0\n" .
                "§c" . $lang->get("reward") . $reward . $lang->get("money");
            $form->addButton($text, 0, "textures/ui/accept", $id);
        }
        $player->sendForm($form);
    }
}