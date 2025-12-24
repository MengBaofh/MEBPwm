<?php

namespace MengBao\MEBPwm\manager;

use pocketmine\utils\Config;
use MengBao\MEBPwm\Main;

class TempAccountManager
{
    private Config $config;
    private array $temp_account;

    public function __construct(Main $plugin)
    {
        $this->config = new Config($plugin->getDataFolder() . "temp_account.yml", Config::YAML);
        $this->temp_account = $this->config->getAll() ?: [];
    }

    /**
     * 设置临时余额
     */
    public function setBalance(string $orderId, float $amount): void
    {
        $this->temp_account[$orderId] = $amount;
    }

    /**
     * 获取临时余额
     */
    public function getBalance(string $orderId): float
    {
        return $this->temp_account[$orderId] ?? 0.0;
    }

    /**
     * 移除临时余额
     */
    public function removeBalance(string $orderId): void
    {
        unset($this->temp_account[$orderId]);
    }

    /**
     * 保存配置
     */
    public function save(): void
    {
        $this->config->setAll($this->temp_account);
        $this->config->save();
    }
}