<?php
/**
 * Gateway 服务（WebSocket 端口）
 * Linux: 由 start.php 统一加载
 * Windows: php extend/workerman/start_gateway.php start
 */
if (!defined('GLOBAL_START')) {
    require_once __DIR__ . '/autoload.php';
}

use GatewayWorker\Gateway;

$gateway = new Gateway("websocket://0.0.0.0:8283");
$gateway->name = 'SgeGateway';
$gateway->count = 4;
$gateway->lanIp = '127.0.0.1';
$gateway->startPort = 3100;
$gateway->registerAddress = '127.0.0.1:1520';

// 心跳检测：55秒间隔，客户端无响应则断开
$gateway->pingInterval = 55;
$gateway->pingNotResponseLimit = 1;
$gateway->pingData = '{"type":"ping"}';

if (!defined('GLOBAL_START')) {
    \Workerman\Worker::runAll();
}
