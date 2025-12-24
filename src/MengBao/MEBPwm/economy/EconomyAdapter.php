<?php
namespace MengBao\MEBPwm\economy;

use pocketmine\Server;

// 经济适配器类，支持 MEBsociety、EconomyAPI 和 BedrockEconomy 插件
class EconomyAdapter {
    private static $instance = null;
    private $economyPlugin;
    private $type;

    const TYPE_MEBSOCIETY = "MEBsociety";
    const TYPE_ECONOMYAPI = "EconomyAPI";
    const TYPE_BEDROCK = "BedrockEconomy";
    const TYPE_NONE = "None";

    private function __construct() {
        $this->detectEconomyPlugin();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function detectEconomyPlugin() {
        $pluginManager = Server::getInstance()->getPluginManager();
        
        if ($pluginManager->getPlugin("MEBsociety") !== null) {
            $this->economyPlugin = $pluginManager->getPlugin("MEBsociety");
            $this->type = self::TYPE_MEBSOCIETY;
        } elseif ($pluginManager->getPlugin("EconomyAPI") !== null) {
            $this->economyPlugin = $pluginManager->getPlugin("EconomyAPI");
            $this->type = self::TYPE_ECONOMYAPI;
        } elseif ($pluginManager->getPlugin("BedrockEconomy") !== null) {
            $this->economyPlugin = $pluginManager->getPlugin("BedrockEconomy");
            $this->type = self::TYPE_BEDROCK;
        } else {
            $this->economyPlugin = null;
            $this->type = self::TYPE_NONE;
        }
    }

    public function getMoney(string $playerName): float {
        switch ($this->type) {
            case self::TYPE_MEBSOCIETY:
                return \MengBao\MEBsociety\Units\Economy::getInstance($this->economyPlugin)->getMoney(strtolower($playerName));
            case self::TYPE_ECONOMYAPI:
                return $this->economyPlugin->myMoney($playerName);
            case self::TYPE_BEDROCK:
                return $this->economyPlugin->getAPI()->getBalance($playerName);
            default:
                return -1;
        }
    }

    public function addMoney(string $playerName, float $amount): bool {
        switch ($this->type) {
            case self::TYPE_MEBSOCIETY:
                return \MengBao\MEBsociety\Units\Economy::getInstance($this->economyPlugin)->addMoney(strtolower($playerName), $amount) === 1;
            case self::TYPE_ECONOMYAPI:
                $this->economyPlugin->addMoney($playerName, $amount);
                return true;
            case self::TYPE_BEDROCK:
                $this->economyPlugin->getAPI()->addToBalance($playerName, $amount);
                return true;
            default:
                return false;
        }
    }

    public function getType(): string {
        return $this->type;
    }

    public function isAvailable(): bool {
        return $this->type !== self::TYPE_NONE;
    }
}
