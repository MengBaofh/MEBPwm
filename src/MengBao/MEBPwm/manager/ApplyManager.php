<?php
// ApplyManager.php
namespace MengBao\MEBPwm\manager;

use pocketmine\utils\Config;
use MengBao\MEBPwm\Main;
use pocketmine\player\Player;

class ApplyManager
{
    private Config $config;
    private Main $plugin;
    private array $applies; // 内存缓存任务数据

    const STATUS_PENDING = "pending";
    const STATUS_ACCEPTED = "accepted";
    const STATUS_REJECTED = "rejected";

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        $this->config = new Config($plugin->getDataFolder() . "applications.yml", Config::YAML);
        $this->applies = $this->config->getAll() ?: [];
    }

    // 创建陪申请
    public function createApply(string $player, string $employer, string $recruitId = ""): string
    {
        $applyId = uniqid("apply_");
        $this->applies[$applyId] = [
            "player" => $player,
            "employer" => $employer,
            "recruitId" => $recruitId,
            "status" => self::STATUS_PENDING,
            "create_time" => time()
        ];
        return $applyId;
    }

    /**
     * 检查玩家是否已对该招募发起待处理申请
     * @param string $player 玩家名（原始大小写）
     * @param string $employer 雇主名（原始大小写）
     * @param string $recruitId 招募ID
     * @return bool true=存在重复申请，false=无重复
     */
    public function hasPendingApply(string $player, string $employer, string $recruitId): bool
    {
        foreach ($this->applies as $apply) {
            // 匹配条件：玩家+雇主+招募ID一致，且申请状态为待处理
            if (
                $apply["player"] === $player
                && $apply["employer"] === $employer
                && $apply["recruitId"] === $recruitId
                && $apply["status"] === self::STATUS_PENDING
            ) {
                return true;
            }
        }
        return false;
    }
    // 通过 applyId 获取 recruitId
    public function getRecruitIdByApplyId(string $applyId): ?string
    {
        $apply = $this->getApply($applyId);
        return $apply ? $apply["recruitId"] : null;
    }

    // 获取雇主的待处理申请
    public function getPendingApplies(string $employer): array
    {
        $applies = [];
        foreach ($this->applies as $id => $apply) {
            if ($apply["employer"] === $employer && $apply["status"] === self::STATUS_PENDING) {
                $applies[$id] = $apply;
            }
        }
        return $applies;
    }

    // 更新申请状态
    public function updateApplyStatus(string $applyId, string $status): void
    {
        if (isset($this->applies[$applyId])) {
            $this->applies[$applyId]["status"] = $status;
        }
    }

    // 获取申请信息
    public function getApply(string $applyId): ?array
    {
        return $this->applies[$applyId] ?? null;
    }

    // 保存配置
    public function save(): void
    {
        $this->config->setAll($this->applies);
        $this->config->save();
    }

    /**
     * 清理过期的已接受或拒绝的申请
     */
    public function cleanExpiredApplies(int $expireTime): void
    {
        foreach ($this->applies as $applyId => $apply) {
            // 清理状态为accepted或rejected且创建时间早于过期时间的申请
            if (in_array($apply["status"], [self::STATUS_ACCEPTED, self::STATUS_REJECTED]) && $apply["create_time"] < $expireTime) {
                unset($this->applies[$applyId]);
            }
        }
    }
}