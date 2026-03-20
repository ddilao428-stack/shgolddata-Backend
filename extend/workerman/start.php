<?php
/**
 * SGE 行情推送服务启动入口（Linux）
 * 用法: php extend/workerman/start.php start [-d]
 */

ini_set('display_errors', 'on');

if (strpos(strtolower(PHP_OS), 'win') === 0) {
    exit("Linux only. Windows 请使用 start_for_win.bat\n");
}

if (!extension_loaded('pcntl')) {
    exit("请安装 pcntl 扩展\n");
}

if (!extension_loaded('posix')) {
    exit("请安装 posix 扩展\n");
}

define('GLOBAL_START', 1);

require_once __DIR__ . '/autoload.php';

// 加载所有启动文件
foreach (glob(__DIR__ . '/start_*.php') as $start_file) {
    require_once $start_file;
}

\Workerman\Worker::runAll();
