<?php

namespace MengBao\MEBPwm\listener;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\block\BaseSign;
use pocketmine\block\utils\SignText;
use pocketmine\player\Player;

use MengBao\MEBPwm\Main;
use MengBao\MEBPwm\form\traits\RecruitDetailFormTrait; // 引入Trait
use MengBao\MEBPwm\form\RatingForm;
use MengBao\MEBPwm\form\RenewalForm;
use MengBao\MEBPwm\form\SettleForm;

class PlayerEventListener implements Listener
{
    use RecruitDetailFormTrait; // 复用Trait中的方法
    private Main $plugin;
    private $lang;

    // 暂存玩家表单提交的告示牌内容（key=玩家名，value=要写入的内容数组）
    private static array $pendingSignContent = [];

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        $this->lang = $plugin->getLanguageManager();
    }

    // 监听告示牌编辑事件，禁止第一行写logo
    public function onPlayerSignChange(SignChangeEvent $event): void
    {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        // 例外场景：插件自身正在写入招募告示牌（暂存内容存在时放行）
        if (isset(self::$pendingSignContent[$playerName])) {
            return;
        }
        // 1. 获取logo和玩家编辑的第一行内容
        $logo = $this->lang->get("logo");
        $playerFirstLine = $event->getNewText()->getLine(0); // 第一行
        // 2. 校验：若第一行匹配logo，取消编辑并提示
        if ($playerFirstLine === $logo) {
            $event->cancel(); // 取消编辑
            $player->sendMessage($this->lang->get("forbid_logo_in_first_line")); // 多语言提示
        }
    }

    /**
     * 暂存玩家的告示牌内容
     */
    public static function setPendingSignContent(Player $player, array $content, string $recruitId): void
    {
        $playerName = $player->getName();
        // 存储结构改为关联数组：包含内容+招募ID
        self::$pendingSignContent[$playerName] = [
            "content" => $content,
            "recruitId" => $recruitId
        ];
    }

    /**
     * 写入内容到告示牌（PM5 标准兼容所有类型）
     * @param BaseSign $block 任意类型告示牌
     * @param array $content 要写入的内容（最多4行）
     */
    private function writeContentToSign(BaseSign $block, array $content): void
    {
        // 1. 处理内容：截取最多4行，补全空行（确保数组长度为4）
        $content = array_slice($content, 0, SignText::LINE_COUNT); // 最多4行
        $content = array_pad($content, SignText::LINE_COUNT, ""); // 不足4行补空

        // 2. 验证内容格式（避免 SignText 构造函数报错）
        foreach ($content as $k => $line) {
            // 移除换行符，确保UTF8编码（符合 SignText 要求）
            $line = str_replace("\n", "", $line);
            if (!mb_check_encoding($line, "UTF-8")) {
                $line = mb_convert_encoding($line, "UTF-8", "auto");
            }
            $content[$k] = $line;
        }

        // 3. 创建 SignText 对象（通过构造函数传入文本行）
        // 保留原告示牌的颜色和发光属性（避免丢失样式）
        $oldText = $block->getText();
        $signText = new SignText(
            $content, // 要设置的文本行数组
            $oldText->getBaseColor(), // 继承原颜色
            $oldText->isGlowing() // 继承原发光属性
        );

        // 4. 应用 SignText 到告示牌并保存
        $block->setText($signText);
        $pos = $block->getPosition();
        $pos->getWorld()->setBlock($pos, $block);
    }

    /**
     * 监听告示牌点击
     */
    public function onPlayerInteract(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        $block = $event->getBlock();

        // 仅处理告示牌类型，非告示牌直接返回
        if (!$block instanceof BaseSign) {
            return;
        }

        $lines = $block->getText();
        $logo = $this->lang->get("logo");
        $isRecruitSign = ($lines->getLine(0) === $logo); // 标记是否为招募告示牌

        // ========== 分支1：玩家创建招募（有暂存内容） ==========
        if (isset(self::$pendingSignContent[$playerName])) {
            // 解析旧ID，检测是否有有效旧招募（防止覆盖）
            $oldShortId = trim(str_replace(["ID：", "ID:"], "", $lines->getLine(1)));
            $oldShortId = preg_replace('/§[0-9a-fA-F]/', '', $oldShortId);
            $hasValidOldRecruit = false;
            if (!empty($oldShortId)) {
                $oldFullId = $this->plugin->getRecruitManager()->getFullIdByShortId($oldShortId);
                if ($oldFullId) {
                    $oldRecruit = $this->plugin->getRecruitManager()->getRecruit($oldFullId);
                    if ($oldRecruit && $oldRecruit["status"] === "pending") {
                        $hasValidOldRecruit = true;
                    }
                }
            }
            // 禁止覆盖有效旧招募
            if ($hasValidOldRecruit) {
                // 1. 获取新招募ID并删除
                $newRecruitId = self::$pendingSignContent[$playerName]["recruitId"];
                $this->plugin->getRecruitManager()->deleteRecruit($newRecruitId); // 调用删除方法
                // 2. 提示玩家（补充新招募已删除的提示）
                $player->sendMessage($this->lang->get("already_bound")); // 原提示：已绑定有效招募
                $player->sendMessage($this->lang->get("new_recruit_deleted")); // 新增多语言提示：新招募已删除
                // 3. 清空暂存数据
                unset(self::$pendingSignContent[$playerName]);
                $event->cancel();
                return;
            }
            // 所有校验通过，写入内容
            $this->writeContentToSign($block, self::$pendingSignContent[$playerName]["content"]); // 读取content字段
            $player->sendMessage($this->lang->get("sign_success"));
            unset(self::$pendingSignContent[$playerName]);
            $event->cancel();
            return;

        }

        // ========== 分支2：玩家查看招募（无暂存内容，仅招募告示牌） ==========
        if (!$isRecruitSign) {
            return; // 非招募告示牌，直接返回（不干扰普通告示牌编辑）
        }
        // 解析ID并校验有效性
        $shortRecruitId = trim(str_replace(["ID：", "ID:"], "", $lines->getLine(1)));
        $shortRecruitId = preg_replace('/§[0-9a-fA-F]/', '', $shortRecruitId);
        // 直接调用Trait的复用方法（传入isShortId=true兼容精简ID）
        self::showRecruitDetailForm($player, $shortRecruitId, true);
        $event->cancel(); // 禁止编辑有效招募告示牌
    }

    /**
     * 玩家登录时触发离线评分任务
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        // ==处理离线评分任务
        $offlineRatingManager = $this->plugin->getOfflineRatingManager();
        $pendingTasks = $offlineRatingManager->getPendingTasks($playerName);
        if (!empty($pendingTasks)) {
            // 提示玩家有未完成的评分任务
            $lang = $this->plugin->getLanguageManager();
            $player->sendMessage($lang->get("rating_pending_tips", ["count" => count($pendingTasks)]));
            // 逐个触发评分窗口（陪玩评雇主）
            foreach ($pendingTasks as $taskId => $task) {
                RatingForm::show(
                    $player,
                    $task["rated"], // 被评分人（雇主）
                    $task["orderId"],
                    "player_rate_employer",
                    $taskId // 传入离线任务ID，用于标记完成/过期
                );
            }
        }

        // ==处理离线结算任务
        $pendingSettles = $this->plugin->getOfflineSettleManager()->getPendingTasks($playerName);
        if (!empty($pendingSettles)) {
            foreach ($pendingSettles as $task) {
                SettleForm::show($player, $task["orderId"]);
                $this->plugin->getOfflineSettleManager()->completeTask($task["id"]);
            }
        }
        // 记录玩家上线时间，用于计算陪玩时长
        $this->plugin->getPlayTimeManager()->onPlayerJoin($playerName);
        
        // ==处理离线续约申请
        $pendingRenewals = $this->plugin->getOfflineRenewalManager()->getPendingRequests($playerName);
        if (!empty($pendingRenewals)) {
            foreach ($pendingRenewals as $reqId => $req) {
                // 推送续约确认表单
                RenewalForm::showConfirm(
                    $player,
                    $req["sender"],
                    $req["orderId"],
                    $req["renew_seconds"]
                );
                // 标记为已处理（避免重复推送）
                $this->plugin->getOfflineRenewalManager()->markProcessed($reqId);
            }
        }
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        // 记录玩家下线时间，用于计算陪玩时长
        $this->plugin->getPlayTimeManager()->onPlayerQuit($playerName);
    }

}