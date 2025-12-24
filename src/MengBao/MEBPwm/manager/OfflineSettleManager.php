<?php
namespace MengBao\MEBPwm\manager;

use MengBao\MEBPwm\Main;
use pocketmine\utils\Config;

class OfflineSettleManager
{
    private Config $config;
    private Main $plugin;
    private array $tasks; // 内存缓存任务数据

    const STATUS_PENDING = "pending";
    const STATUS_COMPLETED = "completed";

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        $this->config = new Config($plugin->getDataFolder() . "offline_settles.yml", Config::YAML);
        // 从文件加载数据到内存
        $this->tasks = $this->config->getAll();
    }

    /**
     * 创建离线结算任务
     * @param string $employer 雇主名称
     * @param string $orderId 订单ID
     * @return string 任务ID
     */
    public function createTask(string $employer, string $orderId): string
    {
        $taskId = uniqid("settle_");
        $this->tasks[$taskId] = [
            "employer" => $employer,
            "orderId" => $orderId,
            "create_time" => time(),
            "status" => self::STATUS_PENDING
        ];
        return $taskId;
    }

    /**
     * 获取指定雇主的所有待处理离线结算任务
     * @param string $employerName 雇主名称
     * @return array 任务列表（key=任务ID，value=任务数据）
     */
    public function getPendingTasks(string $employerName): array
    {
        $pending = [];
        foreach ($this->tasks as $taskId => $task) { // 从内存读取
            if ($task["employer"] === $employerName && $task["status"] === self::STATUS_PENDING) {
                $pending[$taskId] = $task;
            }
        }
        return $pending;
    }

    /**
     * 标记任务为已完成
     * @param string $taskId 任务ID
     */
    public function completeTask(string $taskId): void
    {
        if (isset($this->tasks[$taskId])) {
            $this->tasks[$taskId]["status"] = self::STATUS_COMPLETED;
            $this->tasks[$taskId]["complete_time"] = time();
        }
    }

    /**
     * 获取任务详情
     * @param string $taskId 任务ID
     * @return array|null 任务数据（无则返回null）
     */
    public function getTask(string $taskId): ?array
    {
        return $this->tasks[$taskId] ?? null; // 从内存读取
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