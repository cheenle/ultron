@echo off
REM 启动ollama服务并运行AI增强的DXCC系统（批量处理模式）

echo 🚀 启动Ollama服务...

REM 检查ollama是否已安装
where ollama >nul 2>nul
if %errorlevel% neq 0 (
    echo ⚠️  未找到ollama命令，请确保ollama已安装并添加到PATH
    pause
    exit /b 1
)

REM 检查ollama是否已在运行
tasklist /FI "IMAGENAME eq ollama*" 2>NUL | find /I /N "ollama" >NUL
if "%ERRORLEVEL%"=="0" (
    echo ✅ Ollama服务已在运行
) else (
    echo 启动ollama服务...
    start /min ollama serve
    echo ✅ Ollama服务已启动
    timeout /t 5 /nobreak >nul
)

REM 检查qwen3:4b模型是否已下载
echo 🔍 检查qwen3:4b模型...
ollama list | findstr "qwen3:4b" >nul
if %errorlevel% equ 0 (
    echo ✅ qwen3:4b模型已存在
) else (
    echo ⏳ qwen3:4b模型不存在，正在下载...
    ollama pull qwen3:4b
    if %errorlevel% equ 0 (
        echo ✅ qwen3:4b模型下载完成
    ) else (
        echo ⚠️  qwen3:4b模型下载失败，系统将以传统模式运行
    )
)

echo 🚀 启动AI增强的DXCC系统（批量处理模式）...
echo 💡 关闭此窗口将停止程序
echo 💡 系统将每15秒批量处理一次解码信号

REM 运行AI增强的DXCC系统
php robot_dxcc_enhanced_ollama.php

pause