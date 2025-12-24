<?php
// ApplyForm.php
namespace MengBao\MEBPwm\form;

use pocketmine\player\Player;

use MengBao\MEBPwm\Main;
use MengBao\MEBPwm\manager\ApplyManager;
use jojoe77777\FormAPI\SimpleForm;

class ApplyForm
{
    // 显示申请列表
    public static function show(Player $employer): void
    {
        $plugin = Main::getInstance();
        //获取雇主的所有待处理申请
        $applies = $plugin->getApplyManager()->getPendingApplies($employer->getName());
        // 创建SimpleForm实例展示申请
        $form = new SimpleForm(function (Player $employer, ?int $data) use ($applies) {
            // 表单响应处理
            if ($data === null || !isset($applies[array_keys($applies)[$data]])) {
                return; // 玩家关闭表单或数据无效
            }
            // 获取选中的申请ID
            $applyId = array_keys($applies)[$data];
            self::showDetail($employer, $applyId);
        });
        // 设置表单标题和内容
        $form->setTitle($plugin->getLanguageManager()->get("apply_list_title"));
        $form->setContent($plugin->getLanguageManager()->get("apply_list_content", ["count" => count($applies)]));
        // 添加申请玩家按钮
        foreach ($applies as $apply) {
            $form->addButton($apply["player"], 0); // 第二个参数为按钮图标（0=无图标，可自定义）
        }
        // 发送表单给玩家
        $employer->sendForm($form);
    }

    // 显示申请详情
    public static function showDetail(Player $employer, string $applyId): void
    {
        $plugin = Main::getInstance();
        $apply = $plugin->getApplyManager()->getApply($applyId);
        if (!$apply) {
            $employer->sendMessage($plugin->getLanguageManager()->get("apply_not_found"));
            self::show($employer);
            return;
        }
        $playerName = $apply["player"];  //小写
        // 获取玩家简介信息
        $playTime = $plugin->getPlayTimeManager()->getPlayTime($playerName);
        $score = $plugin->getRatingManager()->getAvgRating($playerName);
        // 创建详情表单
        $form = new SimpleForm(function (Player $employer, ?int $data) use ($applyId, $playerName, $plugin) {
            if ($data === null) {
                self::show($employer); // 关闭表单返回列表
                return;
            }
            // 处理按钮点击
            switch ($data) {
                case 0: // 接受申请
                    self::executeAcceptRecruitCommand($employer,$playerName, $applyId);
                    break;
                case 1: // 拒绝申请
                    $plugin->getApplyManager()->updateApplyStatus($applyId, ApplyManager::STATUS_REJECTED);
                    self::show($employer);
                    break;
                case 2: // 返回列表
                    self::show($employer);
                    break;
            }
        });
        // 设置表单内容
        $form->setTitle($plugin->getLanguageManager()->get("apply_detail_title", ["player" => $playerName]));
        $form->setContent($plugin->getLanguageManager()->get("apply_detail_content", [
            "time" => $playTime,
            "score" => $score
        ]));
        // 添加按钮
        $form->addButton($plugin->getLanguageManager()->get("btn_accept"), 0); // 接受
        $form->addButton($plugin->getLanguageManager()->get("btn_reject"), 0); // 拒绝
        $form->addButton($plugin->getLanguageManager()->get("btn_back"), 0);   // 返回
        // 发送表单
        $employer->sendForm($form);
    }

    /**
     * 接受招募
     */
    private static function executeAcceptRecruitCommand(Player $employer, string $playerName, string $applyId): void
    {
        $plugin = Main::getInstance();
        $employerName = $employer->getName();
        $recruitId = $plugin->getApplyManager()->getRecruitIdByApplyId($applyId);
        // 检查陪玩是否已有雇主
        if ($plugin->getOrderManager()->isPlayerEmployed($playerName)) {
            $employer->sendMessage($plugin->getLanguageManager()->get("player_already_employed"));
            return;
        }
        // 更新申请状态
        $plugin->getApplyManager()->updateApplyStatus($applyId, ApplyManager::STATUS_ACCEPTED);
        // 执行接受命令逻辑
        $command = $plugin->getServer()->getCommandMap()->getCommand("pwm");
        if ($command) {
            $command->execute($employer, "pwm", ["accept", $recruitId, $playerName]);
        } else {
            $employer->sendMessage($plugin->getLanguageManager()->get("command_not_found"));
        }
    }
}