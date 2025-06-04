<?php
declare(strict_types=1);

namespace Performance\Review\Phar\Util;

use Exception;

class MagentoHelper
{
    public static function getEnvConfig(string $magentoRoot): array
    {
        $envFile = $magentoRoot . '/app/etc/env.php';
        if (!file_exists($envFile)) {
            throw new Exception("Magento env.php not found at: $envFile");
        }
        
        return include $envFile;
    }

    public static function getConfigValue(array $env, string $path, $default = null)
    {
        $keys = explode('/', $path);
        $value = $env;
        
        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }
        
        return $value;
    }

    public static function getDatabaseConnection(array $env): ?\PDO
    {
        try {
            $dbConfig = self::getConfigValue($env, 'db/connection/default');
            if (!$dbConfig) {
                return null;
            }

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;port=%s',
                $dbConfig['host'] ?? 'localhost',
                $dbConfig['dbname'] ?? '',
                $dbConfig['port'] ?? '3306'
            );

            return new \PDO(
                $dsn,
                $dbConfig['username'] ?? '',
                $dbConfig['password'] ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getActiveModules(string $magentoRoot): array
    {
        $configFile = $magentoRoot . '/app/etc/config.php';
        if (!file_exists($configFile)) {
            return [];
        }

        $config = include $configFile;
        $modules = [];
        
        foreach ($config['modules'] ?? [] as $module => $enabled) {
            if ($enabled) {
                $modules[] = $module;
            }
        }
        
        return $modules;
    }

    public static function isCoreModule(string $moduleName): bool
    {
        return strpos($moduleName, 'Magento_') === 0;
    }
}