<?php

namespace MengBao\MEBPwm\manager;

use pocketmine\utils\Config;
use pocketmine\player\Player;
use MengBao\MEBPwm\Main;

class TeamManager
{
    private Config $config;
    private $lang;
    // 临时缓存：组队关系（减少文件IO）
    private array $teams = [];
    // 传送冷却（秒），避免频繁传送
    private const TELEPORT_COOLDOWN = 10;
    private array $tpCooldown = [];

    public function __construct(Main $plugin)
    {
        $this->lang = $plugin->getLanguageManager();
        $this->config = new Config(
            $plugin->getDataFolder() . "teams.yml",
            Config::YAML,
            ["teams" => []]
        );
        $this->teams = $this->config->get("teams", []);
    }

    /**
     * 创建组队
     */
    public function createTeam(string $p1, string $p2): string
    {
        $teamId = uniqid("team_");
        $this->teams[$teamId] = [$p1, $p2];
        return $teamId;
    }

    /**
     * 获取所有组队信息
     * @return array 组队列表
     */
    public function getTeams(): array
    {
        return $this->teams;
    }

    /**
     * 检查是否为队友
     */
    public function isTeammate(string $p1, string $p2): bool
    {
        foreach ($this->teams as $team) {
            if (in_array($p1, $team) && in_array($p2, $team)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 校验传送冷却
     * @param Player $player 玩家
     * @return bool 是否可传送（无冷却）
     */
    public function checkTpCooldown(Player $player): bool
    {
        $name = $player->getName();
        if (isset($this->tpCooldown[$name]) && time() - $this->tpCooldown[$name] < self::TELEPORT_COOLDOWN) {
            $remain = self::TELEPORT_COOLDOWN - (time() - $this->tpCooldown[$name]);
            $player->sendMessage("§c传送冷却中！剩余 {$remain} 秒后可再次传送。");
            return false;
        }
        // 更新冷却时间
        $this->tpCooldown[$name] = time();
        return true;
    }

    /**
     * 删除组队
     */
    public function deleteTeam(Player $player): bool
    {
        $playerName = $player->getName();
        foreach ($this->teams as $teamId => $teamMembers) {
            if (in_array($playerName, $teamMembers)) {
                unset($this->teams[$teamId]);
                $this->save();
                return true;
            }
        }
        return false;
    }

    /**
     * 保存组队数据到文件
     */
    public function save(): void
    {
        $this->config->set("teams", $this->teams);
        $this->config->save();
    }
}