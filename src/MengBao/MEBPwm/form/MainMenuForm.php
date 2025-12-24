<?php

namespace MengBao\MEBPwm\form;

use pocketmine\player\Player;
use jojoe77777\FormAPI\SimpleForm;
use MengBao\MEBPwm\Main;
use MengBao\MEBPwm\form\{PublishForm, ApplyForm, PendingSettleForm, RecruitListForm, RankForm};

class MainMenuForm
{
    public static function send(Player $player): void
    {
        $plugin = Main::getInstance();
        $lang = $plugin->getLanguageManager();
        $form = new SimpleForm(function (Player $player, $data) use ($plugin) {
            if ($data === null)
                return;
            switch ($data) {
                case 0:
                    PublishForm::open($player);  // 发布招募
                    break;
                case 1:
                    ApplyForm::show($player);  // 查看申请
                    break;
                case 2:
                    PendingSettleForm::show($player);  // 查看待结算订单
                    break;
                case 3:
                    RecruitListForm::show($player);  // 查看所有的招募
                    break;
                case 4:
                    RankForm::show($player);  // 查看时长榜单
                    break;
                case 5:
                    $teamManager = $plugin->getTeamManager();
                    $p2Name = null;
                    foreach ($teamManager->getTeams() as $team) {
                        if (in_array($player->getName(), $team)) {
                            $p2Name = ($team[0] === $player->getName()) ? $team[1] : $team[0];
                            break;
                        }
                    }
                    if ($p2Name === null) {
                        $player->sendMessage($plugin->getLanguageManager()->get("no_teammate"));
                        return;
                    }
                    // 执行接受命令逻辑
                    $command = $plugin->getServer()->getCommandMap()->getCommand("pwm");
                    if ($command) {
                        $command->execute($player, "pwm", ["tp", $p2Name]);
                    } else {
                        $player->sendMessage($plugin->getLanguageManager()->get("command_not_found"));
                    }
                    break;
            }
        });

        // 多语言标题和按钮
        $form->setTitle($lang->get("main_menu_title"));
        $form->addButton($lang->get("btn_publish_recruit"), 0, "textures/ui/add");
        $form->addButton($lang->get("btn_view_apply"), 0, "textures/ui/list");
        $form->addButton($lang->get("btn_pending_settle"), 0, "textures/ui/list");
        $form->addButton($lang->get("btn_view_recruit"), 0, "textures/ui/list");
        $form->addButton($lang->get("btn_view_rank"), 0, "textures/ui/star");
        $form->addButton($lang->get("btn_tp"), 0, "textures/ui/star");
        $player->sendForm($form);
    }
}