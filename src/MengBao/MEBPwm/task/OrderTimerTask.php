<?php

namespace MengBao\MEBPwm\task;

use pocketmine\scheduler\Task;
use pocketmine\player\Player;
use MengBao\MEBPwm\Main;
use MengBao\MEBPwm\form\SettleForm;

class OrderTimerTask extends Task
{
    private Main $plugin;
    private $lang;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        $this->lang = $plugin->getLanguageManager();
    }

    public function onRun(): void
    {
        $orders = $this->plugin->getOrderManager()->getRunningOrders();
        foreach ($orders as $orderId => $info) {
            // 检查订单是否超过最大时长
            if ($this->plugin->getOrderManager()->isOrderExpired($orderId)) {
                // 冻结订单
                $this->plugin->getOrderManager()->freezeOrder($orderId);

                $employer = $this->plugin->getServer()->getPlayerExact($info["employer"]);
                $companion = $this->plugin->getServer()->getPlayerExact($info["player"]);

                // 通知双方
                $employer && $employer->sendMessage($this->lang->get("order_frozen", ["id" => $orderId]));
                $companion && $companion->sendMessage($this->lang->get("order_companion_frozen", ["id" => $orderId]));

                // 若雇主不在线，创建离线结算任务
                if (!$employer instanceof Player) {
                    $this->plugin->getOfflineSettleManager()->createTask($info["employer"], $orderId);
                    $this->plugin->getLogger()->info("雇主 {$info['employer']} 离线，已创建离线结算任务（订单 {$orderId}）");
                } else {
                    // 雇主在线，直接显示结算表单
                    SettleForm::show($employer, $orderId);
                }
                continue;
            }

            // 只有双方都在线时才减少剩余时间
            $employerOnline = $this->plugin->getServer()->getPlayerExact($info["employer"]) instanceof Player;
            $playerOnline = $this->plugin->getServer()->getPlayerExact($info["player"]) instanceof Player;

            if ($info["remaining_time"] > 0 && $employerOnline && $playerOnline) {
                $this->plugin->getOrderManager()->updateRemainingTime($orderId, $info["remaining_time"] - 30);

                // 时间到提醒
                if ($info["remaining_time"] - 30 <= 0) {
                    $employer = $this->plugin->getServer()->getPlayerExact($info["employer"]);
                    $companion = $this->plugin->getServer()->getPlayerExact($info["player"]);

                    $employer && $employer->sendMessage($this->lang->get("order_time_up", ["id" => $orderId]));
                    $companion && $companion->sendMessage($this->lang->get("order_companion_time_up", ["id" => $orderId]));
                }
            }
        }

        // 检查是否有需要自动结算的过期冻结订单
        $this->plugin->getOrderManager()->autoSettleExpiredOrders();
    }
}