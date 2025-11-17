#!/bin/bash

# ULTRON 启动脚本 - Unix/Linux/macOS版本
# ULTRON - Automatic Control of JTDX/WSJT-X/MSHV

clear

echo "╔══════════════════════════════════════════════════════════════════════════════╗"
echo "║                                                                              ║"
echo "║                                  ULTRON                                      ║"
echo "║                    Automatic Control of JTDX/WSJT-X/MSHV                    ║"
echo "║                                                                              ║"
echo "║                    Python Version - Cross Platform                           ║"
echo "║                                                                              ║"
echo "╚══════════════════════════════════════════════════════════════════════════════╝"
echo ""

# 检查Python版本
check_python() {
    if command -v python3 &> /dev/null; then
        PYTHON_CMD="python3"
    elif command -v python &> /dev/null; then
        PYTHON_CMD="python"
    else
        echo "错误: 未找到Python解释器"
        exit 1
    fi
    
    # 检查Python版本
    PYTHON_VERSION=$($PYTHON_CMD --version 2>&1 | awk '{print $2}')
    PYTHON_MAJOR=$(echo $PYTHON_VERSION | cut -d. -f1)
    PYTHON_MINOR=$(echo $PYTHON_VERSION | cut -d. -f2)
    
    if [ "$PYTHON_MAJOR" -lt 3 ] || ([ "$PYTHON_MAJOR" -eq 3 ] && [ "$PYTHON_MINOR" -lt 7 ]); then
        echo "错误: 需要Python 3.7或更高版本 (当前版本: $PYTHON_VERSION)"
        exit 1
    fi
    
    echo "使用Python版本: $PYTHON_VERSION"
}

# 检查文件
 check_files() {
    if [ ! -f "ultron.py" ]; then
        echo "错误: 找不到ultron.py文件"
        exit 1
    fi
    
    if [ ! -f "base.json" ]; then
        echo "警告: 找不到base.json文件，DXCC功能可能受限"
    fi
}

# 运行标准ULTRON
run_standard() {
    echo "启动标准ULTRON..."
    $PYTHON_CMD ultron.py
}

# 运行增强版ULTRON
run_dxcc() {
    echo "启动增强版ULTRON (DXCC白名单功能)..."
    $PYTHON_CMD ultron_dxcc.py
}

# 运行分析模式
run_analyze() {
    echo "分析DXCC通联情况..."
    $PYTHON_CMD ultron_dxcc.py analyze
}

# 显示菜单
show_menu() {
    echo "ULTRON 启动选项:"
    echo "  1) 标准ULTRON"
    echo "  2) 增强版ULTRON (DXCC白名单)"
    echo "  3) DXCC通联分析"
    echo "  4) 退出"
    echo ""
}

# 主函数
main() {
    # 检查Python
    check_python
    
    # 检查文件
    check_files
    
    # 检查参数
    if [ "$1" = "dxcc" ]; then
        run_dxcc
    elif [ "$1" = "analyze" ]; then
        run_analyze
    elif [ "$1" = "standard" ]; then
        run_standard
    else
        # 显示菜单
        show_menu
        read -p "请输入选择 (1-4): " choice
        
        case $choice in
            1)
                run_standard
                ;;
            2)
                run_dxcc
                ;;
            3)
                run_analyze
                ;;
            4)
                echo "退出"
                exit 0
                ;;
            *)
                echo "无效选择，启动标准ULTRON..."
                run_standard
                ;;
        esac
    fi
}

# 捕获Ctrl+C
trap 'echo -e "\n用户中断，退出程序"; exit 0' INT

# 运行主函数
main "$@"