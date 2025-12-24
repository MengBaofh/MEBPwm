<?php

namespace MengBao\MEBPwm\task;

use MengBao\MEBPwm\Main;
use pocketmine\scheduler\Task;

/**
 * 将超过3d无人接取的pending招募标记为closed
 */
class ExpirePendingRecruitsTask extends Task
{
    /** @var Main 插件主实例 */
    private Main $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * 任务执行逻辑
     */
    public function onRun(): void
    {
        $lang = $this->plugin->getLanguageManager();
        try {
            // 3天的秒数：3 * 86400 = 259200秒
            $expireThreshold = time() - 3 * 86400;
            $recruitManager = $this->plugin->getRecruitManager();
            $allRecruits = $recruitManager->getAllRecruits(); // 获取所有招募记录
            $expiredCount = 0;
            // 遍历所有招募，筛选出超3天的pending招募
            foreach ($allRecruits as $recruitId => $recruit) {
                // 条件：状态为pending + 创建时间早于3天前
                if ($recruit["status"] === "pending" && $recruit["create_time"] < $expireThreshold) {
                    // 标记为closed
                    $recruitManager->updateRecruitStatus($recruitId, "closed");
                    $expiredCount++;
                }
            }
            if ($expiredCount > 0) {
                $this->plugin->getLogger()->info($lang->get("recruit_auto_expire_log", ["count" => $expiredCount]));
            }
        } catch (\Exception $e) {
            $this->plugin->getLogger()->error( $lang->get("recruit_expire_error", ["msg" => $e->getMessage()]));
        }
    }
}