<?php

namespace MengBao\MEBPwm\task;

use MengBao\MEBPwm\Main;
use pocketmine\scheduler\Task;

/**
 * 清理过期7d的 招募\订单\离线评论 的定时任务
 */
class CleanExpiredRORTask extends Task
{
    /** @var Main 插件主实例 */
    private Main $plugin;

    /**
     * 构造函数：接收插件主实例
     * @param Main $plugin
     */
    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onRun(): void
    {
        $lang = $this->plugin->getLanguageManager();
        try {
            $expireTime = time() - 7 * 86400;
            $this->plugin->getOfflineRatingManager()->cleanExpiredTasks($expireTime);
            $this->plugin->getRecruitManager()->cleanExpiredRecruits($expireTime);
            $this->plugin->getOrderManager()->cleanExpiredOrders($expireTime);
            $this->plugin->getLogger()->info($lang->get("ror_clean_success", ["days" => 7]));
        } catch (\Exception $e) {
            // 异常捕获：避免任务崩溃影响服务器
            $this->plugin->getLogger()->error($lang->get("ror_clean_error", ["msg" => $e->getMessage()]));
        }
    }
}