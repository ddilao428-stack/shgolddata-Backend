<?php
/**
 * BusinessWorker 服务
 * Linux: 由 start.php 统一加载
 * Windows: php extend/workerman/start_businessworker.php start
 */
if (!defined('GLOBAL_START')) {
    require_once __DIR__ . '/autoload.php';
}

use GatewayWorker\BusinessWorker;

$worker = new BusinessWorker();
$worker->name = 'SgeBusinessWorker';
$worker->count = 4;
$worker->registerAddress = '127.0.0.1:1520';
$worker->eventHandler = '\\workerman\\Events';

if (!defined('GLOBAL_START')) {
    \Workerman\Worker::runAll();
}
