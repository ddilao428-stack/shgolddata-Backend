<?php
/**
 * Workerman 专用 autoload
 * 避免加载 vendor/autoload.php 时触发 FastAdmin addons 的 ThinkPHP 依赖
 */

$vendorDir = dirname(dirname(__DIR__)) . '/vendor';

// 1. Workerman 自带 Autoloader
require_once $vendorDir . '/workerman/workerman/Autoloader.php';

// 2. 注册 PSR-4 自动加载
spl_autoload_register(function ($class) use ($vendorDir) {
    // GatewayWorker
    if (strpos($class, 'GatewayWorker\\') === 0) {
        $file = $vendorDir . '/workerman/gateway-worker/src/' . str_replace('\\', '/', substr($class, 14)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    // Workerman\MySQL
    if (strpos($class, 'Workerman\\MySQL\\') === 0) {
        $file = $vendorDir . '/workerman/mysql/src/' . str_replace('\\', '/', substr($class, 16)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    // workerman\Events (我们自己的事件处理类)
    if ($class === 'workerman\\Events') {
        $file = dirname(__FILE__) . '/Events.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
