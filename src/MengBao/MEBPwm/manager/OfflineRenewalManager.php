<?php
namespace MengBao\MEBPwm\manager;

use MengBao\MEBPwm\Main;
use pocketmine\utils\Config;

class OfflineRenewalManager
{
    private Main $plugin;
    private Config $config;
    private array $requests; // 内存缓存申请数据

    const STATUS_PENDING = "pending"; // 待处理
    const STATUS_PROCESSED = "processed"; // 已处理

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        // 初始化离线续约申请配置文件
        $this->config = new Config(
            $plugin->getDataFolder() . "offline_renewals.yml",
            Config::YAML,
            ["requests" => []]
        );
        // 从文件加载数据到内存
        $this->requests = $this->config->get("requests", []);
    }

    /**
     * 创建离线续约申请
     * @param string $targetName 被申请人
     * @param string $senderName 申请人
     * @param string $orderId 订单ID
     * @param int $renewSeconds 续约时长（秒）
     * @return string 申请ID
     */
    public function createRequest(string $targetName, string $senderName, string $orderId, int $renewSeconds): string
    {
        $requestId = uniqid("renew_");
        $this->requests[$requestId] = [
            "target" => $targetName,
            "sender" => $senderName,
            "orderId" => $orderId,
            "renew_seconds" => $renewSeconds,
            "create_time" => time(),
            "status" => self::STATUS_PENDING
        ];
        return $requestId;
    }

    /**
     * 获取指定玩家的待处理离线续约申请
     * @param string $playerName 玩家名称
     * @return array 申请列表
     */
    public function getPendingRequests(string $playerName): array
    {
        $pending = [];
        foreach ($this->requests as $id => $req) { // 从内存读取
            if ($req["target"] === $playerName && $req["status"] === self::STATUS_PENDING) {
                $pending[$id] = $req;
            }
        }
        return $pending;
    }

    /**
     * 标记申请为已处理
     * @param string $requestId 申请ID
     */
    public function markProcessed(string $requestId): void
    {
        if (isset($this->requests[$requestId])) {
            $this->requests[$requestId]["status"] = self::STATUS_PROCESSED;
            $this->requests[$requestId]["process_time"] = time();
        }
    }

    /**
     * 保存数据到文件（仅在服务器关闭时调用）
     */
    public function save(): void
    {
        $this->config->set("requests", $this->requests);
        $this->config->save();
    }
}