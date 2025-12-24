<?php

namespace MengBao\MEBPwm\manager;

use pocketmine\utils\Config;
use MengBao\MEBPwm\Main;

class PlayTimeManager
{
    private Config $config;
    private array $lastOnlineTime; // 记录玩家上次在线时间
    private array $playTimes; // 内存缓存时长数据

    public function __construct(Main $plugin)
    {
        $this->config = new Config($plugin->getDataFolder() . "playtimes.yml", Config::YAML);
        // 初始化：加载历史时长到内存
        $this->playTimes = $this->config->getAll() ?: [];
        $this->lastOnlineTime = [];
    }

    /**
     * 记录玩家上线时间
     */
    public function onPlayerJoin(string $player): void
    {
        $this->lastOnlineTime[$player] = time();
    }

    /**
     * 记录玩家下线时间，并更新在线时长
     */
    public function onPlayerQuit(string $player): void
    {
        if (isset($this->lastOnlineTime[$player])) {
            $onlineTime = time() - $this->lastOnlineTime[$player];
            $this->addPlayTime($player, $onlineTime);
            unset($this->lastOnlineTime[$player]);
        }
    }

    /**
     * 获取当前在线的玩家
     */
    public function getOnlinePlayers(): array
    {
        return array_keys($this->lastOnlineTime);
    }

    /**
     * 添加陪玩时长（秒）
     */
    public function addPlayTime(string $player, int $seconds): void
    {
        $this->playTimes[$player] = ($this->playTimes[$player] ?? 0) + $seconds;
    }

    /**
     * 获取玩家时长
     */
    public function getPlayTime(string $player): int
    {
        return $this->playTimes[$player] ?? 0;
    }

    /**
     * 获取所有玩家时长
     */
    public function getAllPlayTimes(): array
    {
        return $this->playTimes;
    }

    /**
     * 保存配置
     */
    public function save(): void
    {
        $this->config->setAll($this->playTimes);
        $this->config->save();
    }
}