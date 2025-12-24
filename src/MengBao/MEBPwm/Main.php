<?php
/*
src/
└── MengBao/
    └── MEBPwm/
        ├── Main.php                            # 主入口类
        ├── command/
        │   └── PwmCommand.php                  # 指令处理类
        ├── economy/
        │   └── EconomyAdapter.php              # 经济适配器类
        ├── listener/
        │   └── PlayerEventListener.php         # 事件监听类
        ├── manager/
        │   ├── ApplyManager.php                # 申请管理
        │   ├── OfflineRatingManager.php        # 离线评分管理
        │   ├── OfflineRenewalManager.php       # 离线续约管理
        │   ├── OfflineSettleManager.php        # 离线结算管理
        │   ├── RecruitManager.php              # 招募管理
        │   ├── OrderManager.php                # 订单管理
        │   ├── RatingManager.php               # 评分管理
        │   ├── PlayTimeManager.php             # 时长管理
        │   ├── TempAccountManager.php          # 临时账户管理
        │   ├── TeamManager.php                 # 组队管理
        │   └── LanguageManager.php             # 多语言管理
        ├── form/
        │   ├── ApplyForm.php                   # 申请表单
        │   ├── MainMenuForm.php                # 主菜单表单
        │   ├── PendingSettleForm.php           # 待结算订单表单
        │   ├── PublishForm.php                 # 发布招募表单
        │   ├── RecruitListForm.php             # 招募列表表单
        │   ├── RankForm.php                    # 时长榜单表单
        │   ├── RatingForm.php                  # 评分表单
        │   ├── RenewalForm.php                 # 续约表单
        │   ├── SettleForm.php                  # 结算表单
        │   └── traits/                         # 表单共用Traits
        │       └── RecruitDetailFormTrait.php  # 招募详情表单Trait
        └── task/
            ├── ExpirePendingRecruitsTask.php   # 过期pending招募任务
            ├── CleanExpiredApplyTask.php       # 清理过期申请任务
            ├── CleanExpiredRORTask.php         # 清理过期招募/订单/离线任务
            └── OrderTimerTask.php              # 订单计时任务
resources/
├── language/                 # 语言文件目录
│   ├── zh_CN.yml             # 简体中文
│   └── en_US.yml             # 英文
└── config.yml                 # 插件主配置文件
*/
namespace MengBao\MEBPwm;

use pocketmine\plugin\PluginBase;

use MengBao\MEBPwm\command\PwmCommand;
use MengBao\MEBPwm\listener\PlayerEventListener;
use MengBao\MEBPwm\manager\{
    ApplyManager,
    RecruitManager,
    OrderManager,
    RatingManager,
    OfflineRatingManager,
    PlayTimeManager,
    TempAccountManager,
    TeamManager,
    LanguageManager,
    OfflineSettleManager,
    OfflineRenewalManager
};
use MengBao\MEBPwm\task\OrderTimerTask;
use MengBao\MEBPwm\task\CleanExpiredRORTask;
use MengBao\MEBPwm\task\ExpirePendingRecruitsTask;
use MengBao\MEBPwm\task\CleanExpiredApplyTask;
use MengBao\MEBPwm\economy\EconomyAdapter;

class Main extends PluginBase
{
    /** @var self 单例实例 */
    private static self $instance;

    /** @var RecruitManager 招募管理器 */
    private RecruitManager $recruitManager;

    /** @var OrderManager 订单管理器 */
    private OrderManager $orderManager;

    /** @var RatingManager 评分管理器 */
    private RatingManager $ratingManager;

    /** @var OfflineRatingManager 离线评分管理器 */
    private OfflineRatingManager $offlineRatingManager;

    /** @var OfflineSettleManager 离线结算管理器 */
    private OfflineSettleManager $offlineSettleManager;

    /** @var OfflineRenewalManager 离线续约管理器 */
    private OfflineRenewalManager $offlineRenewalManager;

    /** @var ApplyManager 陪玩申请管理器 */
    private ApplyManager $applyManager;

    /** @var PlayTimeManager 时长管理器 */
    private PlayTimeManager $playTimeManager;

    /** @var TempAccountManager 临时账户管理器 */
    private TempAccountManager $tempAccountManager;

    /** @var TeamManager 组队管理器 */
    private TeamManager $teamManager;

    /** @var LanguageManager 多语言管理器 */
    private LanguageManager $languageManager;

    private int $maxEmploy;

    public function onEnable(): void
    {
        self::$instance = $this;

        // 初始化插件主配置文件（包含默认语言配置）
        $this->saveDefaultConfig(); // 生成默认config.yml
        $config = $this->getConfig();

        // 初始化数据文件夹
        $this->getDataFolder() && !is_dir($this->getDataFolder()) && mkdir($this->getDataFolder(), 0777, true);

        // 初始化管理器
        $defaultLang = $config->get("default_language", "zh_CN"); // 读取配置中的默认语言
        $this->applyManager = new ApplyManager($this);
        $this->languageManager = new LanguageManager($this);
        $this->languageManager->setDefaultLang($defaultLang); // 应用配置的默认语言
        $this->recruitManager = new RecruitManager($this);
        $this->orderManager = new OrderManager($this);
        $this->ratingManager = new RatingManager($this);
        $this->offlineRatingManager = new OfflineRatingManager($this);
        $this->playTimeManager = new PlayTimeManager($this);
        $this->tempAccountManager = new TempAccountManager($this);
        $this->teamManager = new TeamManager($this);
        $this->offlineSettleManager = new OfflineSettleManager($this);
        $this->offlineRenewalManager = new OfflineRenewalManager($this);
        EconomyAdapter::getInstance();  // 初始化经济适配器
        $this->registerCommands();
        $this->getServer()->getPluginManager()->registerEvents(new PlayerEventListener($this), $this);
        // 启动订单定时检查任务（每30秒检查一次订单超时）
        $this->getScheduler()->scheduleRepeatingTask(new OrderTimerTask($this), 30 * 20);
        // 每小时检查一次招募状态，标记超过3天未接取的为closed
        $this->getScheduler()->scheduleRepeatingTask(new ExpirePendingRecruitsTask($this), 3600 * 20);
        // 每天凌晨清理7天前的已关闭招募和订单
        $this->getScheduler()->scheduleRepeatingTask(new CleanExpiredRORTask($this), 86400 * 20); // 执行周期：20ticks/秒 × 86400秒 = 1天
        // 每天凌晨清理1天前的未处理申请
        $this->getScheduler()->scheduleRepeatingTask(new CleanExpiredApplyTask($this), 86400 * 20);
    }

    public function onDisable(): void
    {
        // 保存所有管理器数据
        $this->applyManager->save();
        $this->orderManager->save();
        $this->playTimeManager->save();
        $this->ratingManager->save();
        $this->recruitManager->save();
        $this->teamManager->save();
        $this->tempAccountManager->save();
        // 离线管理器的保存
        $this->offlineRatingManager->save();
        $this->offlineSettleManager->save();
        $this->offlineRenewalManager->save();
    }

    /**
     * 注册指令
     */
    private function registerCommands(): void
    {
        $commandMap = $this->getServer()->getCommandMap();
        $commandMap->register("pwm", new PwmCommand($this));
    }

    /**
     * 获取单例
     */
    public static function getInstance(): self
    {
        return self::$instance;
    }

    public function getOfflineRenewalManager(): OfflineRenewalManager
    {
        return $this->offlineRenewalManager;
    }

    public function getOfflineSettleManager(): OfflineSettleManager
    {
        return $this->offlineSettleManager;
    }

    public function getApplyManager(): ApplyManager
    {
        return $this->applyManager;
    }

    public function getLanguageManager(): LanguageManager
    {
        return $this->languageManager;
    }

    public function getRecruitManager(): RecruitManager
    {
        return $this->recruitManager;
    }

    public function getOrderManager(): OrderManager
    {
        return $this->orderManager;
    }

    public function getRatingManager(): RatingManager
    {
        return $this->ratingManager;
    }

    public function getOfflineRatingManager(): OfflineRatingManager
    {
        return $this->offlineRatingManager;
    }

    public function getPlayTimeManager(): PlayTimeManager
    {
        return $this->playTimeManager;
    }

    public function getTempAccountManager(): TempAccountManager
    {
        return $this->tempAccountManager;
    }

    public function getTeamManager(): TeamManager
    {
        return $this->teamManager;
    }

    /**
     * 公共方法获取插件根目录路径（解决protected方法访问问题）
     */
    public function getPluginRootPath(): string
    {
        return $this->getFile(); // Main是PluginBase子类，可合法调用
    }

    /**
     * 获取插件资源目录路径（语言文件源目录）
     */
    public function getResourceLangPath(): string
    {
        // 插件内部资源目录（phar包内/源码目录）的language文件夹
        return $this->getPluginRootPath() . "language/";
    }
    public function getMaxEmploy(): int
    {
        return $this->maxEmploy;
    }
}