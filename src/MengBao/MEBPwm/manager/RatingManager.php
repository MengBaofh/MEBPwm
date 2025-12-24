<?php

namespace MengBao\MEBPwm\manager;

use pocketmine\utils\Config;
use MengBao\MEBPwm\Main;

class RatingManager
{
    private Config $config;
    private array $ratings; // 内存缓存

    public function __construct(Main $plugin)
    {
        $this->config = new Config($plugin->getDataFolder() . "ratings.yml", Config::YAML);
        $this->ratings = $this->config->getAll() ?: [];
    }

    /**
     * 添加评分
     */
    public function addRating(string $targetPlayer, string $rater, float $score, string $comment = ""): void
    {
        $score = max(1, min(5, $score)); // 强制限制在1-5分
        $this->ratings[$targetPlayer][] = [
            "rater" => $rater,
            "score" => $score,
            "comment" => $comment,
            "time" => time() // 记录评分时间
        ];
    }
    /**
     * 获取平均分
     */
    public function getAvgRating(string $player): float
    {
        // 读取该玩家的所有评分记录
        $allRatings = $this->getAllRatingsForPlayer($player);
        // 无评分记录时返回默认值5.0
        if (empty($allRatings)) {
            return 5.0;
        }
        // 计算所有评分的总和
        $totalScore = 0;
        foreach ($allRatings as $rating) {
            $totalScore += $rating["score"] ?? 0; // 兼容无score的异常数据
        }
        // 计算平均分并保留1位小数
        $avg = $totalScore / count($allRatings);
        return round($avg, 1);
    }

    // 获取所有玩家对目标玩家的评分/评论
    public function getAllRatingsForPlayer(string $targetPlayer): array
    {
        return $this->ratings[$targetPlayer] ?? [];
    }

    /**
     * 保存配置
     */
    public function save(): void
    {
        $this->config->setAll($this->ratings);
        $this->config->save();
    }
}