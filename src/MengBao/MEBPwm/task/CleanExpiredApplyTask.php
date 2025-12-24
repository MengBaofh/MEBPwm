<?php

namespace MengBao\MEBPwm\task;

use MengBao\MEBPwm\Main;
use pocketmine\scheduler\Task;

/**
 * 清理过期1d的 申请 的定时任务
 */
class CleanExpiredApplyTask extends Task
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
            $expireTime = time() - 1 * 86400;
            $this->plugin->getApplyManager()->cleanExpiredApplies($expireTime);
            $this->plugin->getLogger()->info($lang->get("a_clean_success", ["days" => 1]));
        } catch (\Exception $e) {
            // 异常捕获：避免任务崩溃影响服务器
            $this->plugin->getLogger()->error($lang->get("a_clean_error", ["msg" => $e->getMessage()]));
        }
    }
}