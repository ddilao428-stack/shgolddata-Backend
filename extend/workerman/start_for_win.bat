@echo off
REM SGE 行情推送服务 - Windows 启动脚本
REM Windows 下需要分开启动每个服务（每个服务一个窗口）
REM 使用方法：双击此文件 或 在 Backend 目录下执行

echo ========================================
echo   SGE 行情推送服务 (Windows)
echo ========================================
echo.

REM 切换到 Backend 根目录
cd /d "%~dp0\..\.."

echo [1/3] 启动 Register 服务 (端口 1510)...
start "SGE-Register" php extend/workerman/start_register.php start

REM 等待 Register 启动
timeout /t 2 /nobreak >nul

echo [2/3] 启动 Gateway 服务 (WebSocket 端口 8282)...
start "SGE-Gateway" php extend/workerman/start_gateway.php start

REM 等待 Gateway 启动
timeout /t 2 /nobreak >nul

echo [3/3] 启动 BusinessWorker 服务...
start "SGE-BusinessWorker" php extend/workerman/start_businessworker.php start

echo.
echo 所有服务已启动！
echo - Register:       端口 1510 (内部通信)
echo - Gateway:        端口 8282 (WebSocket)
echo - BusinessWorker: 事件处理
echo.
echo 关闭所有 cmd 窗口即可停止服务
pause
