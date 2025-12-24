<?php
namespace MengBao\MEBPwm\form;

use pocketmine\player\Player;
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;
use MengBao\MEBPwm\Main;
use MengBao\MEBPwm\form\traits\RecruitDetailFormTrait;

class RenewalForm
{
    use RecruitDetailFormTrait; // 复用Trait中的方法
    /**
     * 发送续约申请
     * @param Player $sender 申请人（雇主/陪玩均可发起）
     * @param string $targetName 对方玩家名称（陪玩/雇主）
     * @param string $orderId 订单ID
     */
    public static function sendRequest(Player $sender, string $targetName, string $orderId): void
    {
        $plugin = Main::getInstance();
        $lang = $plugin->getLanguageManager(); // 获取语言管理器
        $order = $plugin->getOrderManager()->getOrder($orderId);

        // 基础校验
        if (!$order) {
            $sender->sendMessage($lang->get("renewal_order_not_exist", ["id" => $orderId]));
            return;
        }
        if ($order["status"] === "completed") {
            $sender->sendMessage($lang->get("renewal_order_completed"));
            return;
        }

        // 1. 构建续约时长选择表单（申请人选择续约时长）
        $form = new CustomForm(function (Player $sender, ?array $data) use ($targetName, $orderId, $plugin, $lang) {
            if ($data === null) {
                $sender->sendMessage($lang->get("renewal_canceled"));
                return;
            }

            // $data[0] = 续约时长（小时），限制1-72小时（3天）
            $renewHours = (int) $data[0];
            if ($renewHours < 1 || $renewHours > 72) {
                $sender->sendMessage($lang->get("renewal_hours_invalid"));
                return;
            }
            $renewSeconds = $renewHours * 3600; // 转换为秒

            // 2. 查找目标玩家（在线则直接发送申请，离线则记录离线申请）
            $targetPlayer = $plugin->getServer()->getPlayerExact($targetName);
            if ($targetPlayer instanceof Player) {
                // 目标在线：展示续约确认表单
                self::showConfirm($targetPlayer, $sender->getName(), $orderId, $renewSeconds);
                $sender->sendMessage($lang->get("renewal_request_sent", [
                    "target" => $targetName,
                    "hours" => $renewHours
                ]));
            } else {
                // 目标离线：创建离线续约申请
                $plugin->getOfflineRenewalManager()->createRequest(
                    $targetName,
                    $sender->getName(),
                    $orderId,
                    $renewSeconds
                );
                $sender->sendMessage($lang->get("renewal_request_saved", ["target" => $targetName]));
            }
        });

        // 表单UI配置（续约时长选择：滑块，1-72小时，步长1，默认24小时）
        $form->setTitle($lang->get("renewal_request_title", ["id" => $orderId]));
        $form->addSlider(
            $lang->get("renewal_select_hours"),
            1,          // 最小值（1小时）
            72,         // 最大值（72小时=3天）
            1,          // 步长
            24          // 默认值（24小时）
        );
        $form->addLabel($lang->get("renewal_order_info", [
            "status" => $order['status'],
            "time" => self::formatSeconds($order['remaining_time'])
        ]));
        // 发送时长选择表单给申请人
        $sender->sendForm($form);
        $sender->sendMessage($lang->get("renewal_select_hours_tip", ["target" => $targetName]));
        return;
    }

    /**
     * 展示续约申请确认表单（给被申请人）
     * @param Player $target 被申请人（陪玩）
     * @param string $senderName 申请人名称
     * @param string $orderId 订单ID
     * @param int $renewSeconds 续约时长（秒，用于实际更新）
     */
    public static function showConfirm(Player $target, string $senderName, string $orderId, int $renewSeconds): void
    {
        $plugin = Main::getInstance();
        $order = $plugin->getOrderManager()->getOrder($orderId);
        $lang = $plugin->getLanguageManager();
        $renewHours = $renewSeconds / 3600;
        $form = new SimpleForm(function (Player $target, ?int $data) use ($senderName, $orderId, $renewHours, $renewSeconds, $order, $plugin, $lang) {
            if ($data === null) {
                // 关闭表单 = 拒绝
                $sender = $plugin->getServer()->getPlayerExact($senderName);
                $sender?->sendMessage($lang->get("renewal_not_processed", [
                    "target" => $target->getName(),
                    "id" => $orderId
                ]));
                $target->sendMessage($lang->get("renewal_confirm_canceled"));
                return;
            }

            switch ($data) {
                case 0: // 确认续约
                    // 更新订单时长（核心逻辑）
                    $plugin->getOrderManager()->renewOrder($orderId, $renewSeconds, $senderName);
                    // 通知双方
                    $target->sendMessage($lang->get("renewal_confirmed_target", [
                        "sender" => $senderName,
                        "id" => $orderId,
                        "hours" => $renewHours
                    ]));
                    $sender = $plugin->getServer()->getPlayerExact($senderName);
                    $sender?->sendMessage($lang->get("renewal_confirmed_sender", [
                        "target" => $target->getName(),
                        "id" => $orderId,
                        "hours" => $renewHours
                    ]));
                    // 如果订单之前是冻结状态，解冻
                    if ($order["status"] === "frozen") {
                        $plugin->getOrderManager()->unfreezeOrder($orderId);
                        $target->sendMessage($lang->get("renewal_order_unfrozen"));
                        $sender?->sendMessage($lang->get("renewal_order_unfrozen_sender", ["id" => $orderId]));
                    }
                    break;

                case 1: // 拒绝续约
                    $target->sendMessage($lang->get("renewal_rejected_target", [
                        "sender" => $senderName,
                        "id" => $orderId
                    ]));
                    $sender = $plugin->getServer()->getPlayerExact($senderName);
                    $sender?->sendMessage($lang->get("renewal_rejected_sender", [
                        "target" => $target->getName(),
                        "id" => $orderId,
                        "hours" => $renewHours
                    ]));
                    break;
            }
        });

        // 确认表单UI
        $form->setTitle($lang->get("renewal_confirm_title", ["id" => $orderId]));
        $form->setContent($lang->get("renewal_confirm_content", [
            "sender" => $senderName,
            "id" => $orderId,
            "hours" => $renewHours,
            "status" => $order['status']
        ]));
        $form->addButton($lang->get("btn_confirm_renewal"));
        $form->addButton($lang->get("btn_reject_renewal"));
        $target->sendForm($form);
    }
}
