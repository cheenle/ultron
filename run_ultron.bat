@echo off
chcp 65001 >nul 2>&1
setlocal enabledelayedexpansion

:: ULTRON 启动脚本 - Windows版本
:: ULTRON - Automatic Control of JTDX/WSJT-X/MSHV

echo ╔══════════════════════════════════════════════════════════════════════════════╗
echo ║                                                                              ║
echo ║                                  ULTRON                                      ║
echo ║                    Automatic Control of JTDX/WSJT-X/MSHV                    ║
echo ║                                                                              ║
echo ║                    Python Version - Cross Platform                           ║
echo ║                                                                              ║
echo ╚══════════════════════════════════════════════════════════════════════════════╝
echo.

:: 检查Python版本
set "PYTHON_CMD="
for %%p in (python python3 py) do (
    where %%p >nul 2>&1
    if !errorlevel! equ 0 (
        set "PYTHON_CMD=%%p"
        goto :found_python
    )
)

if "%PYTHON_CMD%"=="" (
    echo 错误: 未找到Python解释器
    echo 请确保Python已安装并添加到PATH环境变量
    pause
    exit /b 1
)

:found_python
:: 检查Python版本
for /f "tokens=2" %%v in ('%PYTHON_CMD% --version 2^>^&1') do set PYTHON_VERSION=%%v
echo 使用Python版本: %PYTHON_VERSION%

:: 检查Python版本是否足够新
for /f "tokens=1,2 delims=." %%a in ("%PYTHON_VERSION%") do (
    set /a MAJOR=%%a
    set /a MINOR=%%b
)

if %MAJOR% lss 3 (
    echo 错误: 需要Python 3.7或更高版本 ^(当前版本: %PYTHON_VERSION%^)
    pause
    exit /b 1
)

if %MAJOR% equ 3 (
    if %MINOR% lss 7 (
        echo 错误: 需要Python 3.7或更高版本 ^(当前版本: %PYTHON_VERSION%^)
        pause
        exit /b 1
    )
)

:: 检查文件
if not exist "ultron.py" (
    echo 错误: 找不到ultron.py文件
    pause
    exit /b 1
)

if not exist "base.json" (
    echo 警告: 找不到base.json文件，DXCC功能可能受限
)

:: 检查参数
if "%1"=="dxcc" goto :run_dxcc
if "%1"=="analyze" goto :run_analyze
if "%1"=="standard" goto :run_standard

:: 显示菜单
echo ULTRON 启动选项:
echo   1 - 标准ULTRON
echo   2 - 增强版ULTRON (DXCC白名单功能)
echo   3 - DXCC通联分析
echo.

set /p choice=请选择启动模式 (1-3): 

if "%choice%"=="1" goto :run_standard
if "%choice%"=="2" goto :run_dxcc
if "%choice%"=="3" goto :run_analyze

:: 默认选择
echo 无效选择，启动标准ULTRON...
goto :run_standard

:run_standard
echo 启动标准ULTRON...
%PYTHON_CMD% ultron.py
pause
exit /b 0

:run_dxcc
echo 启动增强版ULTRON (DXCC白名单功能)...
%PYTHON_CMD% ultron_dxcc.py
pause
exit /b 0

:run_analyze
echo 分析DXCC通联情况...
%PYTHON_CMD% ultron_dxcc.py analyze
pause
exit /b 0