<?php

namespace MengBao\MEBPwm\manager;

use MengBao\MEBPwm\Main;
use pocketmine\utils\Config;

class OfflineRatingManager
{
    private Config $config;
    private Main $plugin;
    private array $tasks; // 内存缓存任务数据

    // 评分任务状态：pending(待评分)、completed(已评分)、expired(已过期)
    const STATUS_PENDING = "pending";
    const STATUS_COMPLETED = "completed";
    const STATUS_EXPIRED = "expired";

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        $this->config = new Config($plugin->getDataFolder() . "offline_ratings.yml", Config::YAML);
        // 从文件加载数据到内存
        $this->tasks = $this->config->getAll() ?: [];
    }

    /**
     * 创建离线评分任务
     * @param string $rater 待评分玩家（陪玩/雇主）
     * @param string $rated 被评分玩家（雇主/陪玩）
     * @param string $orderId 关联订单ID
     * @return string 任务ID
     */
    public function createTask(string $rater, string $rated, string $orderId): string
    {
        $taskId = uniqid("rating_");
        $this->tasks[$taskId] = [
            "rater" => $rater,
            "rated" => $rated,
            "orderId" => $orderId,
            "create_time" => time(),
            "status" => self::STATUS_PENDING
        ];
        return $taskId;
    }

    /**
     * 获取玩家的未完成评分任务
     * @param string $player 玩家名（小写）
     * @return array 任务列表
     */
    public function getPendingTasks(string $player): array
    {
        $pending = [];
        foreach ($this->tasks as $taskId => $task) {
            if ($task["rater"] === $player && $task["status"] === self::STATUS_PENDING) {
                $pending[$taskId] = $task;
            }
        }
        return $pending;
    }

    /**
     * 更新任务状态（完成/过期）
     * @param string $taskId 任务ID
     * @param string $status 状态
     */
    public function updateTaskStatus(string $taskId, string $status): void
    {
        if (isset($this->tasks[$taskId])) {
            $this->tasks[$taskId]["status"] = $status;
        }
    }

    /**
     * 清理超时任务（7天未评分自动过期）
     */
    public function cleanExpiredTasks($expireTime): void
    {
        foreach ($this->tasks as $taskId => $task) {
            if ($task["create_time"] < $expireTime && $task["status"] === self::STATUS_PENDING) {
                $this->tasks[$taskId]["status"] = self::STATUS_EXPIRED;
            }
        }
    }

    /**
     * 保存数据到文件（仅在服务器关闭时调用）
     */
    public function save(): void
    {
        $this->config->setAll($this->tasks);
        $this->config->save();
    }
}