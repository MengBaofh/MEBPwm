<?php

namespace MengBao\MEBPwm\manager;

use pocketmine\utils\Config;
use MengBao\MEBPwm\Main;

class LanguageManager
{
    private Main $plugin;
    private Config $langConfig;
    private string $defaultLang = "zh_CN"; // 默认语言
    private array $languageData = [];

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        // 创建语言文件夹
        $langDir = $plugin->getDataFolder() . "language/";
        if (!is_dir($langDir)) {
            mkdir($langDir, 0777, true);
        }
        // 复制默认语言文件（如果不存在）
        $this->copyDefaultLangFiles();
        // 加载默认语言
        $this->loadLanguage($this->defaultLang);
    }

    /**
     * 复制默认语言文件到插件目录
     */
    private function copyDefaultLangFiles(): void
    {
        $langFiles = ["zh_CN.yml", "en_US.yml"];
        // 获取插件内部资源的language目录（通过Main的公共方法）
        $sourceLangDir = $this->plugin->getResourceLangPath();

        foreach ($langFiles as $file) {
            // 源文件路径（插件内置的语言文件）
            $source = $sourceLangDir . $file;
            // 目标文件路径（插件数据目录）
            $target = $this->plugin->getDataFolder() . "language/" . $file;

            // 兼容源码运行和phar包运行两种场景
            if (!file_exists($target)) {
                // 如果源码目录不存在，尝试从插件资源中提取
                if ($this->plugin->getResource("language/" . $file)) {
                    $this->plugin->saveResource("language/" . $file);
                }
            }
        }
    }

    /**
     * 加载指定语言
     */
    public function loadLanguage(string $langCode): void
    {
        $langFile = $this->plugin->getDataFolder() . "language/" . $langCode . ".yml";
        if (!file_exists($langFile)) {
            $this->plugin->getLogger()->warning("Language file {$langCode}.yml not found, use default language");
            $langFile = $this->plugin->getDataFolder() . "language/" . $this->defaultLang . ".yml";
        }

        $this->langConfig = new Config($langFile, Config::YAML);
        $this->languageData = $this->langConfig->getAll();
        $this->plugin->getLogger()->info("Loaded language: {$langCode}");
    }

    /**
     * 获取语言文本（支持变量替换）
     */
    public function get(string $key, array $params = []): string
    {
        $text = $this->languageData[$key] ?? "§c[Missing Language: {$key}]";

        // 替换变量 {xxx}
        foreach ($params as $param => $value) {
            $text = str_replace("{" . $param . "}", $value, $text);
        }

        return $text;
    }

    /**
     * 设置默认语言
     */
    public function setDefaultLang(string $langCode): void
    {
        $this->defaultLang = $langCode;
        $this->loadLanguage($langCode);
    }

    /**
     * 获取当前默认语言
     */
    public function getDefaultLang(): string
    {
        return $this->defaultLang;
    }
}