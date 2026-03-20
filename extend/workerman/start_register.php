<?php
/**
 * Register 服务
 * Linux: 由 start.php 统一加载
 * Windows: php extend/workerman/start_register.php start
 */
if (!defined('GLOBAL_START')) {
    require_once __DIR__ . '/autoload.php';
}

use GatewayWorker\Register;

$register = new Register('text://0.0.0.0:1520');

if (!defined('GLOBAL_START')) {
    \Workerman\Worker::runAll();
}
