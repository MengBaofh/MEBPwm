<?php
namespace MengBao\MEBPwm\form;

use pocketmine\player\Player;
use jojoe77777\FormAPI\SimpleForm;
use MengBao\MEBPwm\Main;
use MengBao\MEBPwm\form\traits\RecruitDetailFormTrait;

class PendingSettleForm
{
    use RecruitDetailFormTrait; // 复用Trait中的方法
    public static function show(Player $player): void
    {
        $plugin = Main::getInstance();
        $lang = $plugin->getLanguageManager();
        $orderManager = $plugin->getOrderManager();

        // 获取玩家的待结算订单
        $pendingOrders = [];
        foreach ($orderManager->getAllOrders() as $id => $order) {
            if (
                ($order["status"] === "frozen" || $order["status"] === "running") &&
                ($order["employer"] === $player->getName() || $order["player"] === $player->getName())
            ) {
                $pendingOrders[$id] = $order;
            }
        }

        $form = new SimpleForm(function (Player $player, ?int $data) use ($pendingOrders) {
            if ($data === null || !isset($pendingOrders[array_keys($pendingOrders)[$data]])) {
                return;
            }

            $orderId = array_keys($pendingOrders)[$data];
            self::showDetail($player, $orderId);
        });

        $form->setTitle($lang->get("pending_settle_title"));
        $form->setContent($lang->get("pending_settle_content", ["count" => count($pendingOrders)]));

        foreach ($pendingOrders as $id => $order) {
            $otherParty = $order["employer"] === $player->getName() ? $order["player"] : $order["employer"];
            $form->addButton("{$id}: {$otherParty}");
        }

        $player->sendForm($form);
    }

    public static function showDetail(Player $player, string $orderId): void
    {
        $plugin = Main::getInstance();
        $lang = $plugin->getLanguageManager();
        $order = $plugin->getOrderManager()->getOrder($orderId);
        if (!$order) {
            $player->sendMessage($lang->get("order_not_exist"));
            return;
        }

        $form = new SimpleForm(function (Player $player, ?int $data) use ($orderId, $order) {
            if ($data === null)
                return;

            switch ($data) {
                case 0: // 结算
                    $plugin = Main::getInstance();
                    if ($order["employer"] === $player->getName()) {
                        SettleForm::show($player, $orderId);
                    } else {
                        $player->sendMessage($plugin->getLanguageManager()->get("only_employer_can_settle"));
                    }
                    break;
                case 1: // 续约申请
                    $plugin = Main::getInstance();
                    $otherParty = $order["employer"] === $player->getName() ? $order["player"] : $order["employer"];
                    RenewalForm::sendRequest($player, $otherParty, $orderId);
                    break;
            }
        });

        $form->setTitle($lang->get("order_detail_title", ["id" => $orderId]));
        $form->setContent(
            $lang->get("employer") . $order["employer"] . "\n" .
            $lang->get("player") . $order["player"] . "\n" .
            $lang->get("status") . $lang->get($order["status"]) . "\n" .
            $lang->get("remaining_time") . self::formatSeconds($order["remaining_time"])
        );

        $form->addButton($lang->get("btn_settle"));
        $form->addButton($lang->get("btn_renewal"));

        $player->sendForm($form);
    }
}