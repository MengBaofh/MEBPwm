<?php
namespace MengBao\MEBPwm\form;

use pocketmine\player\Player;
use jojoe77777\FormAPI\CustomForm;
use MengBao\MEBPwm\Main;
use MengBao\MEBPwm\economy\EconomyAdapter;

class SettleForm {
    /**
     * 向玩家展示结算表单
     * @param Player $employer 雇主玩家（只有雇主能发起结算）
     * @param string $orderId 订单ID
     */
    public static function show(Player $employer, string $orderId): void {
        $plugin = Main::getInstance();
        $orderManager = $plugin->getOrderManager();
        $economy = EconomyAdapter::getInstance();
        $lang = $plugin->getLanguageManager();

        // 1. 校验订单是否存在且状态合法
        $order = $orderManager->getOrder($orderId);
        if (!$order) {
            $employer->sendMessage("§c错误：订单 {$orderId} 不存在！");
            return;
        }
        if ($order["employer"] !== $employer->getName()) {
            $employer->sendMessage("§c错误：只有雇主可以发起结算！");
            return;
        }
        if ($order["status"] === "completed") {
            $employer->sendMessage("§c错误：该订单已完成结算！");
            return;
        }

        // 2. 构建结算表单（自定义表单：进度选择 + 确认结算）
        $form = new CustomForm(function (Player $employer, ?array $data) use ($orderId, $order, $plugin, $economy) {
            // 玩家关闭表单时返回
            if ($data === null) {
                $employer->sendMessage("§e结算已取消！");
                return;
            }

            // 3. 处理表单提交数据（$data[0] = 完成进度百分比）
            $progress = (int)$data[0];
            // 进度范围校验（0-100）
            if ($progress < 0 || $progress > 100) {
                $employer->sendMessage("§c错误：完成进度必须在 0-100 之间！");
                return;
            }

            // 4. 计算应结算金额（总报酬 × 进度百分比）
            $totalReward = (float)$order["reward"];
            $settleAmount = $totalReward * ($progress / 100);
            $settleAmount = round($settleAmount, 2); // 保留2位小数

            // 5. 执行经济结算（转账给陪玩者）
            $companion = $order["player"];
            if (!$economy->isAvailable()) {
                $employer->sendMessage("§c错误：未检测到可用的经济插件（MEBsociety/EconomyAPI）！");
                return;
            }

            // 转账操作
            $transferSuccess = $economy->addMoney($companion, $settleAmount);
            if (!$transferSuccess) {
                $employer->sendMessage("§c结算失败：向 {$companion} 转账 {$settleAmount} 失败！");
                return;
            }

            // 6. 更新订单状态（标记为已完成）
            $plugin->getOrderManager()->completeOrder(
                $orderId,
                $progress,
                $employer,
                $companion
            );

            // 7. 通知双方
            $employer->sendMessage("§a结算成功！订单 {$orderId} 已完成，结算金额：{$settleAmount}（完成度：{$progress}%）");
            $companionPlayer = $plugin->getServer()->getPlayerExact($companion);
            if ($companionPlayer instanceof Player) {
                $companionPlayer->sendMessage("§a你收到结算款项：{$settleAmount}（订单 {$orderId}，完成度：{$progress}%）");
            }

            // 8. 解除组队
            $plugin->getTeamManager()->deleteTeam($employer);
        });

        // 表单UI配置
        $form->setTitle("订单结算 - {$orderId}");
        $form->addSlider(
            "完成进度（%）", // 滑块标题
            0,              // 最小值
            100,            // 最大值
            1,              // 步长
            100             // 默认值（100%）
        );
        $form->addLabel("订单信息：\n雇主：{$order['employer']}\n陪玩：{$order['player']}\n总报酬：{$order['reward']}");
        // 发送表单给玩家
        $employer->sendForm($form);
    }
}
