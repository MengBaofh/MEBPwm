<?php

namespace MengBao\MEBPwm\manager;

use pocketmine\utils\Config;
use pocketmine\player\Player;

use MengBao\MEBPwm\Main;
use MengBao\MEBPwm\form\RatingForm;

class OrderManager
{
    private Main $plugin;
    private Config $config;
    private array $orders; // 内存缓存所有订单数据
    // 添加最大时长常量（3天，单位：秒）
    const MAX_ORDER_DURATION = 259200; // 3 * 24 * 60 * 60

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        $this->config = new Config($plugin->getDataFolder() . "orders.yml", Config::YAML);
        // 初始化：文件数据加载到内存（仅一次）
        $this->orders = $this->config->getAll() ?: [];
    }

    /**
     * 创建订单
     */
    public function createOrder(string $id, string $recruitId, string $employer, string $player, int $duration, float $reward): void
    {
        $this->orders[$id] = [
            "recruit_id" => $recruitId,
            "employer" => $employer,
            "player" => $player,
            "duration" => $duration,
            "remaining_time" => $duration,
            "reward" => $reward,
            "status" => "running",
            "start_time" => time()
        ];
    }

    /**
     * 获取订单信息
     */
    public function getOrder(string $id): ?array
    {
        return $this->orders[$id] ?? null;
    }

    /**
     * 获取所有订单信息
     */
    public function getAllOrders(): array
    {
        return $this->orders;
    }

    /**
     * 获取所有运行中的订单
     */
    public function getRunningOrders(): array
    {
        $orders = [];
        foreach ($this->orders as $id => $info) {
            if ($info["status"] === "running") {
                $orders[$id] = $info;
            }
        }
        return $orders;
    }

    /**
     * 更新剩余时间
     */
    public function updateRemainingTime(string $id, int $time): void
    {
        if (isset($this->orders[$id])) {
            $this->orders[$id]["remaining_time"] = $time;
        }
    }

    /**
     * 完成订单（双向评分触发核心）
     * @param string $orderId 订单ID
     * @param Player $employer 雇主（在线）
     * @param string $playerName 陪玩玩家名（可能离线）
     */
    public function completeOrder(string $orderId, int $completeRate, Player $employer, string $playerName): void
    {
        if (isset($this->orders[$orderId])) {
            $this->orders[$orderId]["status"] = "completed";
            $this->orders[$orderId]["complete_rate"] = $completeRate;
        }
        // 触发雇主对陪玩的实时评分（雇主在线）
        RatingForm::show($employer, $playerName, $orderId, "employer_rate_player"); // 标记评分类型：雇主评陪玩
        // 处理陪玩对雇主的评分（兼容离线）
        $player = $this->plugin->getServer()->getPlayerExact($playerName);
        if ($player instanceof Player && $player->isOnline()) {
            // 陪玩在线：实时触发评分（陪玩评雇主）
            RatingForm::show($player, $employer->getName(), $orderId, "player_rate_employer");
        } else {
            // 陪玩离线：创建离线评分任务
            $this->plugin->getOfflineRatingManager()->createTask($playerName, $employer->getName(), $orderId);
            $this->plugin->getLogger()->info("陪玩玩家 {$playerName} 离线，已创建评分任务（评雇主 {$employer->getName()}）");
        }
    }

    /**
     * 检查陪玩是否已有雇主
     */
    public function isPlayerEmployed(string $player): bool
    {
        foreach ($this->getRunningOrders() as $order) {
            if ($order["player"] === $player) {
                return true;
            }
        }
        return false;
    }

    // 添加检查订单是否过期
    public function isOrderExpired(string $orderId): bool
    {
        $order = $this->getOrder($orderId);
        if (!$order)
            return false;

        $currentTime = time();
        $orderDuration = $currentTime - $order["start_time"];
        return $orderDuration >= self::MAX_ORDER_DURATION;
    }

    // 自动结算过期订单
    public function autoSettleExpiredOrders(): void
    {
        $expireTime = time() - 7 * 86400; // 7天前
        foreach ($this->getAllOrders() as $id => $info) {
            if ($info["status"] === "frozen" && $info["frozen_time"] < $expireTime) {
                // 7天未结算，自动按100%结算
                $this->completeOrder($id, 100, $info["employer"], $info["player"]);
                $this->plugin->getLogger()->info("订单 {$id} 已超过7天未结算，自动按100%完成度结算");
            }
        }
    }

    // 冻结订单
    public function freezeOrder(string $orderId): void
    {
        if (isset($this->orders[$orderId])) {
            $this->orders[$orderId]["status"] = "frozen";
            $this->orders[$orderId]["frozen_time"] = time();
        }
    }

    /**
     * 续约订单（增加时长）
     * @param string $orderId 订单ID
     * @param int $addSeconds 新增时长（秒）
     * @param string $playmentName 陪玩名称
     */
    public function renewOrder(string $orderId, int $addSeconds, string $playmentName): void
    {
        $order = $this->getOrder($orderId);
        if (!$order) {
            $this->plugin->getLogger()->warning("续约失败：订单 {$orderId} 不存在");
            return;
        }
        // 1. 更新剩余时长（如果有），否则更新开始时间
        if (isset($order["remaining_time"])) {
            $newRemaining = $order["remaining_time"] + $addSeconds;
            $this->orders[$orderId]["remaining_time"] = $newRemaining; // 同步内存
        } else {
            // 重置开始时间（延长订单有效期）
            $this->orders[$orderId]["start_time"] = time();
        }
        // 2. 记录续约信息
        $renewals = $order["renewals"] ?? [];
        $renewals[] = [
            "time" => time(),
            "add_seconds" => $addSeconds,
            "operator" => $playmentName ?? "system"
        ];
        $this->orders[$orderId]["renewals"] = $renewals;
    }

    /**
     * 解冻订单（从frozen状态恢复为running）
     * @param string $orderId 订单ID
     */
    public function unfreezeOrder(string $orderId): void
    {
        $order = $this->getOrder($orderId);
        if (!$order || $order["status"] !== "frozen")
            return;
        $this->orders[$orderId]["status"] = "running";
        unset($this->orders[$orderId]["frozen_time"]); // 移除冻结时间
    }

    /**
     * 保存配置
     */
    public function save(): void
    {
        $this->config->setAll($this->orders);
        $this->config->save();
    }

    /**
     * 清理过期的已关闭订单
     */
    public function cleanExpiredOrders(int $expireTime): void
    {
        foreach ($this->getAllOrders() as $id => $info) {
            if ($info["status"] === "completed" && $info["start_time"] < $expireTime) {
                unset($this->orders[$id]);
            }
        }
    }
}