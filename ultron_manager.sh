#!/bin/bash

# ULTRON DXCC 白名单管理系统
# 版本: 2.0 - 增强版
# 功能: 启动/停止/重启/状态/更新白名单

# 配置
ROBOT_PID_FILE="ultron.pid"
ROBOT_LOG_FILE="robot_output.log"
DXCC_LOG_FILE="wsjtx_log.adi"
PHP_INI="extra/php-lnx.ini"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m' # No Color

# 函数: 显示标题
show_header() {
    clear
    echo -e "${CYAN}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║                     🚀 ULTRON DXCC 管理系统                  ║${NC}"
    echo -e "${CYAN}║                    版本: 2.0 - 增强版                       ║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════════════════╝${NC}"
    echo
}

# 函数: 显示菜单
show_menu() {
    echo -e "${WHITE}请选择操作:${NC}"
    echo -e "${GREEN}  1)${NC} 启动 ULTRON DXCC 增强版"
    echo -e "${GREEN}  2)${NC} 停止 ULTRON"
    echo -e "${GREEN}  3)${NC} 重启 ULTRON"
    echo -e "${GREEN}  4)${NC} 查看状态"
    echo -e "${GREEN}  5)${NC} 更新白名单"
    echo -e "${GREEN}  6)${NC} 查看实时日志"
    echo -e "${GREEN}  7)${NC} 查看通联统计"
    echo -e "${GREEN}  8)${NC} 运行 DXCC 分析"
    echo -e "${GREEN}  9)${NC} 清理日志文件"
    echo -e "${GREEN}  0)${NC} 退出"
    echo
    echo -n "请输入选择 [0-9]: "
}

# 函数: 检查是否在运行
is_running() {
    if [ -f "$ROBOT_PID_FILE" ]; then
        local pid=$(cat "$ROBOT_PID_FILE")
        if ps -p "$pid" > /dev/null 2>&1; then
            return 0
        else
            rm -f "$ROBOT_PID_FILE"
            return 1
        fi
    fi
    return 1
}

# 函数: 获取进程信息
get_process_info() {
    if is_running; then
        local pid=$(cat "$ROBOT_PID_FILE")
        local start_time=$(ps -p "$pid" -o lstart= 2>/dev/null)
        local cpu=$(ps -p "$pid" -o %cpu= 2>/dev/null)
        local mem=$(ps -p "$pid" -o %mem= 2>/dev/null)
        echo -e "${GREEN}运行中${NC} (PID: $pid, CPU: ${cpu}%, MEM: ${mem}%, 启动: $start_time)"
    else
        echo -e "${RED}已停止${NC}"
    fi
}

# 函数: 启动ULTRON
start_ultron() {
    echo -e "${YELLOW}正在启动 ULTRON DXCC 增强版...${NC}"
    
    if is_running; then
        echo -e "${RED}ULTRON 已经在运行中！${NC}"
        return 1
    fi
    
    # 检查PHP配置
    if [ ! -f "$PHP_INI" ]; then
        echo -e "${RED}错误: 找不到PHP配置文件 $PHP_INI${NC}"
        return 1
    fi
    
    # 检查白名单文件
    if [ ! -f "dxcc_whitelist_global.json" ]; then
        echo -e "${RED}错误: 找不到白名单文件 dxcc_whitelist_global.json${NC}"
        echo -e "${YELLOW}请先运行白名单更新功能${NC}"
        return 1
    fi
    
    # 启动ULTRON
    echo -e "${BLUE}使用配置: $PHP_INI${NC}"
    nohup php -c "$PHP_INI" robot_dxcc.php > "$ROBOT_LOG_FILE" 2>&1 &
    local pid=$!
    
    # 保存PID
    echo $pid > "$ROBOT_PID_FILE"
    
    sleep 2
    
    if is_running; then
        echo -e "${GREEN}✅ ULTRON DXCC 增强版启动成功！${NC}"
        echo -e "${CYAN}PID: $pid${NC}"
        echo -e "${CYAN}日志文件: $ROBOT_LOG_FILE${NC}"
        
        # 显示启动信息
        sleep 1
        echo
        echo -e "${WHITE}启动信息:${NC}"
        tail -n 20 "$ROBOT_LOG_FILE" | grep -E "(白名单|DXCC|实体|启动)" | tail -n 5
    else
        echo -e "${RED}❌ 启动失败，请检查日志${NC}"
        rm -f "$ROBOT_PID_FILE"
        return 1
    fi
}

# 函数: 停止ULTRON
stop_ultron() {
    echo -e "${YELLOW}正在停止 ULTRON...${NC}"
    
    if ! is_running; then
        echo -e "${RED}ULTRON 没有在运行${NC}"
        return 1
    fi
    
    local pid=$(cat "$ROBOT_PID_FILE")
    echo -e "${BLUE}停止进程 PID: $pid${NC}"
    
    # 优雅停止
    kill -TERM "$pid" 2>/dev/null
    
    # 等待进程结束
    local count=0
    while is_running && [ $count -lt 10 ]; do
        sleep 1
        count=$((count + 1))
    done
    
    # 如果还在运行，强制停止
    if is_running; then
        echo -e "${YELLOW}强制停止进程...${NC}"
        kill -KILL "$pid" 2>/dev/null
        sleep 1
    fi
    
    rm -f "$ROBOT_PID_FILE"
    
    if ! is_running; then
        echo -e "${GREEN}✅ ULTRON 已停止${NC}"
    else
        echo -e "${RED}❌ 停止失败${NC}"
        return 1
    fi
}

# 函数: 重启ULTRON
restart_ultron() {
    echo -e "${YELLOW}正在重启 ULTRON...${NC}"
    stop_ultron
    sleep 2
    start_ultron
}

# 函数: 显示状态
show_status() {
    echo -e "${WHITE}ULTRON 状态信息:${NC}"
    echo -e "===================="
    echo -e "运行状态: $(get_process_info)"
    
    if is_running; then
        local pid=$(cat "$ROBOT_PID_FILE")
        echo -e "进程PID: $pid"
        
        # 显示白名单统计
        if [ -f "dxcc_whitelist_global.json" ]; then
            local whitelist_count=$(grep -c '"name"' dxcc_whitelist_global.json 2>/dev/null || echo "0")
            echo -e "全局白名单: ${GREEN}$whitelist_count 个实体${NC}"
        fi
        
        # 显示已通联统计
        if [ -f "dxcc_worked_cache.json" ]; then
            local worked_count=$(grep -c '"name"' dxcc_worked_cache.json 2>/dev/null || echo "0")
            echo -e "已通联缓存: ${CYAN}$worked_count 个实体${NC}"
        fi
        
        # 显示日志文件大小
        if [ -f "$ROBOT_LOG_FILE" ]; then
            local log_size=$(du -h "$ROBOT_LOG_FILE" 2>/dev/null | cut -f1)
            echo -e "日志大小: $log_size"
        fi
        
        # 显示最近活动
        echo
        echo -e "${WHITE}最近活动:${NC}"
        tail -n 10 "$ROBOT_LOG_FILE" 2>/dev/null | grep -E "(CQ|QSO|DXCC|通联|响应)" | tail -n 5
    fi
}

# 函数: 更新白名单
update_whitelist() {
    echo -e "${YELLOW}正在更新DXCC白名单...${NC}"
    
    # 检查文件是否存在
    if [ ! -f "generate_full_unworked_whitelist.php" ]; then
        echo -e "${RED}错误: 找不到白名单生成器${NC}"
        return 1
    fi
    
    # 备份当前白名单
    if [ -f "dxcc_whitelist_global.json" ]; then
        local backup_file="dxcc_whitelist_global.json.backup.$(date +%Y%m%d%H%M%S)"
        cp "dxcc_whitelist_global.json" "$backup_file"
        echo -e "${BLUE}已备份当前白名单: $backup_file${NC}"
    fi
    
    # 运行白名单更新
    echo -e "${CYAN}运行白名单生成器...${NC}"
    php generate_full_unworked_whitelist.php
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ 白名单更新成功！${NC}"
        
        # 显示新白名单统计
        if [ -f "dxcc_whitelist_global.json" ]; then
            local new_count=$(grep -c '"name"' dxcc_whitelist_global.json 2>/dev/null || echo "0")
            echo -e "${GREEN}新的全局白名单: $new_count 个实体${NC}"
        fi
    else
        echo -e "${RED}❌ 白名单更新失败${NC}"
        return 1
    fi
}

# 函数: 查看实时日志
view_logs() {
    echo -e "${WHITE}ULTRON 实时日志:${NC}"
    echo -e "===================="
    
    if [ ! -f "$ROBOT_LOG_FILE" ]; then
        echo -e "${RED}日志文件不存在${NC}"
        return 1
    fi
    
    echo -e "${YELLOW}按 Ctrl+C 退出日志查看${NC}"
    echo
    
    # 显示最新日志并实时更新
    tail -f "$ROBOT_LOG_FILE" 2>/dev/null | grep -E "(DXCC|QSO|CQ|通联|白名单|响应|实体)" --color=always
}

# 函数: 显示通联统计
show_qso_stats() {
    echo -e "${WHITE}DXCC 通联统计:${NC}"
    echo -e "=================="
    
    if [ ! -f "$DXCC_LOG_FILE" ]; then
        echo -e "${RED}找不到日志文件: $DXCC_LOG_FILE${NC}"
        return 1
    fi
    
    echo -e "${CYAN}正在分析ADIF日志...${NC}"
    
    # 总QSO数量
    local total_qso=$(grep -c "<call:" "$DXCC_LOG_FILE" 2>/dev/null || echo "0")
    echo -e "总QSO数量: ${GREEN}$total_qso${NC}"
    
    # 不同波段QSO
    echo
    echo -e "${WHITE}波段统计:${NC}"
    for band in 160m 80m 40m 30m 20m 17m 15m 12m 10m 6m; do
        local count=$(grep -c "<band:$band" "$DXCC_LOG_FILE" 2>/dev/null || echo "0")
        if [ "$count" -gt 0 ]; then
            echo -e "  $band: $count"
        fi
    done
    
    # 不同模式QSO
    echo
    echo -e "${WHITE}模式统计:${NC}"
    for mode in FT8 FT4 JT65 JT9 FST4 Q65 MSK144; do
        local count=$(grep -c "<mode:$mode" "$DXCC_LOG_FILE" 2>/dev/null || echo "0")
        if [ "$count" -gt 0 ]; then
            echo -e "  $mode: $count"
        fi
    done
    
    # 最近10个QSO
    echo
    echo -e "${WHITE}最近通联:${NC}"
    grep -E "<call:.*>.*<dxcc:" "$DXCC_LOG_FILE" 2>/dev/null | tail -n 10 | while read line; do
        local call=$(echo "$line" | sed 's/.*<call:[0-9]*>\([^\u003c]*\).*/\1/')
        local dxcc=$(echo "$line" | sed 's/.*<dxcc:[0-9]*>\([^\u003c]*\).*/\1/')
        local band=$(echo "$line" | sed 's/.*<band:[0-9]*>\([^\u003c]*\).*/\1/')
        local mode=$(echo "$line" | sed 's/.*<mode:[0-9]*>\([^\u003c]*\).*/\1/')
        local date=$(echo "$line" | sed 's/.*<qso_date:[0-9]*>\([^\u003c]*\).*/\1/')
        echo -e "  $call ($dxcc) - $band $mode $date"
    done
}

# 函数: 运行DXCC分析
run_dxcc_analysis() {
    echo -e "${YELLOW}运行DXCC分析器...${NC}"
    
    if [ -f "dxcc_analyzer.php" ]; then
        php dxcc_analyzer.php
    else
        echo -e "${RED}找不到DXCC分析器${NC}"
        return 1
    fi
}

# 函数: 清理日志
cleanup_logs() {
    echo -e "${YELLOW}清理日志文件...${NC}"
    
    # 备份当前日志
    if [ -f "$ROBOT_LOG_FILE" ]; then
        local backup_log="robot_output.log.backup.$(date +%Y%m%d%H%M%S)"
        mv "$ROBOT_LOG_FILE" "$backup_log"
        echo -e "${BLUE}已备份日志: $backup_log${NC}"
    fi
    
    # 创建新日志文件
    touch "$ROBOT_LOG_FILE"
    echo -e "${GREEN}✅ 日志清理完成${NC}"
}

# 主循环
main() {
    while true; do
        show_header
        show_menu
        
        read choice
        echo
        
        case $choice in
            1)
                start_ultron
                ;;
            2)
                stop_ultron
                ;;
            3)
                restart_ultron
                ;;
            4)
                show_status
                ;;
            5)
                update_whitelist
                ;;
            6)
                view_logs
                ;;
            7)
                show_qso_stats
                ;;
            8)
                run_dxcc_analysis
                ;;
            9)
                cleanup_logs
                ;;
            0)
                echo -e "${GREEN}感谢使用 ULTRON DXCC 管理系统！${NC}"
                exit 0
                ;;
            *)
                echo -e "${RED}无效选择，请重新输入${NC}"
                ;;
        esac
        
        echo
        echo -n "按回车键继续..."
        read
    done
}

# 检查root权限
if [ "$EUID" -eq 0 ]; then
    echo -e "${RED}警告: 不建议以root权限运行ULTRON${NC}"
    echo -n "是否继续? (y/N): "
    read confirm
    if [ "$confirm" != "y" ] && [ "$confirm" != "Y" ]; then
        exit 1
    fi
fi

# 运行主程序
main