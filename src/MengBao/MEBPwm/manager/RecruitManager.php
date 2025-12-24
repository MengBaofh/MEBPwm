<?php

namespace MengBao\MEBPwm\manager;

use pocketmine\utils\Config;
use MengBao\MEBPwm\Main;

// 仅在服务器正常关闭时保存
class RecruitManager
{
    private Config $config;
    // 精简ID映射的配置节点名（避免和招募ID冲突）
    private const SHORT_ID_MAPPING_KEY = "short_id_mapping";

    private array $recruits; // 内存缓存招募数据
    public function __construct(Main $plugin)
    {
        $this->config = new Config($plugin->getDataFolder() . "recruits.yml", Config::YAML);
        $this->recruits = $this->config->getAll() ?: [];
        // 初始化映射节点（若不存在则创建空数组）
        if (!isset($this->recruits[self::SHORT_ID_MAPPING_KEY])) {
            $this->recruits[self::SHORT_ID_MAPPING_KEY] = [];
        }
    }

    /**
     * 创建招募
     */
    public function createRecruit(string $id, string $employer, string $content, int $duration, float $reward): void
    {
        // 存储招募信息
        $this->recruits[$id] = [
            "employer" => $employer,
            "content" => $content,
            "duration" => $duration,
            "reward" => $reward,
            "status" => "pending",
            "create_time" => time()
        ];
        // 生成精简ID并存储映射
        $shortId = substr($id, -8); // 取完整ID后8位作为精简ID
        $shortIdMapping = $this->recruits[self::SHORT_ID_MAPPING_KEY]; // 读取现有映射
        $shortIdMapping[$shortId] = $id; // 新增映射关系
        $this->recruits[self::SHORT_ID_MAPPING_KEY] = $shortIdMapping; // 写回配置
    }

    /**
     * 通过精简ID获取完整ID
     */
    public function getFullIdByShortId(string $shortId): ?string
    {
        $shortIdMapping = $this->recruits[self::SHORT_ID_MAPPING_KEY];
        return $shortIdMapping[$shortId] ?? null; // 无映射返回null
    }

    /**
     * 获取招募信息
     */
    public function getRecruit(string $id): ?array
    {
        return $this->recruits[$id] ?? null;
    }

    /**
     * 获取所有招募信息
     */
    public function getAllRecruits(): array
    {
        $recruits = [];
        foreach ($this->recruits as $id => $info) {
            // 排除精简ID映射节点
            if ($id === self::SHORT_ID_MAPPING_KEY) {
                continue;
            }
            if (is_array($info)) {
                $recruits[$id] = $info;
            }
        }
        return $recruits;
    }

    /**
     * 获取所有待接受招募
     */
    public function getAllPendingRecruits(): array
    {
        $recruits = [];
        foreach ($this->recruits as $id => $info) {
            // 1. 排除精简ID映射节点
            if ($id === self::SHORT_ID_MAPPING_KEY) {
                continue;
            }
            // 2. 校验status字段是否存在，避免未定义键错误
            if (is_array($info) && isset($info["status"]) && $info["status"] === "pending") {
                $recruits[$id] = $info;
            }
        }
        return $recruits;
    }

    /**
     * 更新招募状态
     */
    public function updateRecruitStatus(string $id, string $status): void
    {
        if ($this->getRecruit($id)) {
            $this->recruits[$id]["status"] = $status;
        }
    }



    // 删除招募（同时删除精简ID映射）
    public function deleteRecruit(string $id): void
    {
        if ($this->getRecruit($id)) {
            // 删除招募信息
            unset($this->recruits[$id]);
            // 删除对应的精简ID映射
            $shortId = substr($id, -8);
            $shortIdMapping = $this->recruits[self::SHORT_ID_MAPPING_KEY];
            if (isset($shortIdMapping[$shortId])) {
                unset($shortIdMapping[$shortId]);
                $this->recruits[self::SHORT_ID_MAPPING_KEY] = $shortIdMapping;
            }
        }
    }

    /**
     * 清理过期的已关闭招募
     */
    public function cleanExpiredRecruits(int $expireTime): void
    {
        $recruits = $this->getAllRecruits(); // 获取所有招募
        foreach ($recruits as $recruitId => $recruit) {
            // 清理状态为closed且创建时间早于过期时间的招募
            if ($recruit["status"] === "closed" && $recruit["create_time"] < $expireTime) {
                $this->deleteRecruit($recruitId); // 执行删除
            }
        }
    }

    /**
     * 保存配置
     */
    public function save(): void
    {
        $this->config->setAll($this->recruits);
        $this->config->save();
    }
}