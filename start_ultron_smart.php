<?php
/**
 * ULTRON 智能启动脚本
 * 
 * 每次启动时：
 * 1. 分析当前ADI日志
 * 2. 基于传播条件生成智能白名单
 * 3. 启动增强版机器人
 */

// 设置基本参数
error_reporting(E_ALL);
date_default_timezone_set("UTC");

// 颜色输出函数
function fg($text, $color = 'white')
{
    $colors = array(
        'black' => "[30m",
        'red' => "[31m",
        'green' => "[32m",
        'yellow' => "[33m",
        'blue' => "[34m",
        'magenta' => "[35m",
        'cyan' => "[36m",
        'white' => "[37m",
        'gray' => "[90m",
        'bright_green' => "[91m"
    );
    
    $color_code = isset($colors[$color]) ? $colors[$color] : $colors['white'];
    return chr(27) . "$color_code" . "$text" . chr(27) . "[0m\n\r";
}

// 显示启动横幅
function showBanner() {
    $banner = "
╔══════════════════════════════════════════════════════════════╗
║                                                              ║
║                    🚀 ULTRON 智能启动器                     ║
║                                                              ║
║           基于ADI日志和AI分析的智能白名单系统              ║
║                                                              ║
╚══════════════════════════════════════════════════════════════╝
";
    echo fg($banner, 'cyan');
}

// 检查系统状态
function checkSystemStatus() {
    echo fg("🔍 检查系统状态...", 'yellow');
    
    $issues = array();
    $warnings = array();
    
    // 检查必需文件
    $required_files = array(
        'base.json' => '呼号数据库',
        'generate_smart_whitelist.php' => '智能白名单生成器',
        'whitelist_manager.php' => '白名单管理器',
        'robot_dxcc_enhanced.php' => '增强版机器人'
    );
    
    foreach ($required_files as $file => $description) {
        if (!file_exists($file)) {
            $issues[] = "缺失 $description ($file)";
        }
    }
    
    // 检查可选文件
    $optional_files = array(
        'wsjtx_log.adi' => 'ADI日志文件',
        'dxcc_latest.json' => '最新DXCC数据'
    );
    
    foreach ($optional_files as $file => $description) {
        if (!file_exists($file)) {
            $warnings[] = "缺少 $description ($file)";
        }
    }
    
    // 显示结果
    if (empty($issues)) {
        echo fg("   ✅ 系统状态正常", 'green');
    } else {
        foreach ($issues as $issue) {
            echo fg("   ❌ $issue", 'red');
        }
        return false;
    }
    
    if (!empty($warnings)) {
        foreach ($warnings as $warning) {
            echo fg("   ⚠️  $warning", 'yellow');
        }
    }
    
    return true;
}

// 分析当前时间条件
function analyzeCurrentConditions() {
    $hour = date('H');
    $month = date('n');
    $season = getSeason($month);
    
    $conditions = array(
        'hour' => $hour,
        'month' => $month,
        'season' => $season,
        'description' => '',
        'active_bands' => array(),
        'favorable_regions' => array()
    );
    
    // 根据时间确定活跃波段
    if ($hour >= 6 && $hour <= 18) {
        $conditions['description'] = '白天 - 高波段活跃';
        $conditions['active_bands'] = ['15m', '12m', '10m', '6m'];
    } elseif ($hour >= 20 || $hour <= 4) {
        $conditions['description'] = '夜间 - 低波段活跃';
        $conditions['active_bands'] = ['160m', '80m', '40m', '30m'];
    } else {
        $conditions['description'] = '黄昏 - 多波段开放';
        $conditions['active_bands'] = ['20m', '17m', '15m', '40m'];
    }
    
    // 根据季节确定有利区域
    switch ($season) {
        case 'spring':
            $conditions['favorable_regions'] = ['南美', '非洲', '大洋洲'];
            break;
        case 'summer':
            $conditions['favorable_regions'] = ['欧洲', '亚洲', '非洲'];
            break;
        case 'autumn':
            $conditions['favorable_regions'] = ['北美', '亚洲', '大洋洲'];
            break;
        case 'winter':
            $conditions['favorable_regions'] = ['南美', '非洲', '南极洲'];
            break;
    }
    
    return $conditions;
}

// 获取季节
function getSeason($month) {
    if ($month >= 3 && $month <= 5) return 'spring';
    if ($month >= 6 && $month <= 8) return 'summer';
    if ($month >= 9 && $month <= 11) return 'autumn';
    return 'winter';
}

// 生成智能白名单
function generateSmartWhitelist() {
    echo fg("\n🧠 生成智能白名单...", 'cyan');
    
    // 运行智能生成器
    $output = array();
    $return_var = 0;
    exec('php generate_smart_whitelist.php 2>&1', $output, $return_var);
    
    if ($return_var !== 0) {
        echo fg("   ❌ 智能白名单生成失败", 'red');
        return false;
    }
    
    // 显示生成结果
    foreach ($output as $line) {
        if (strpos($line, '全球白名单:') !== false || 
            strpos($line, '已通联:') !== false ||
            strpos($line, '传播条件:') !== false) {
            echo fg("   $line", 'green');
        }
    }
    
    return true;
}

// 验证白名单文件
function validateWhitelists() {
    echo fg("\n🔍 验证白名单文件...", 'yellow');
    
    $whitelist_files = array(
        'dxcc_whitelist_global.json' => '全球白名单',
        'dxcc_whitelist_20m.json' => '20米波段白名单',
        'dxcc_whitelist_40m.json' => '40米波段白名单',
        'dxcc_whitelist_80m.json' => '80米波段白名单'
    );
    
    $all_valid = true;
    $total_entities = 0;
    
    foreach ($whitelist_files as $file => $description) {
        if (!file_exists($file)) {
            echo fg("   ❌ 缺失 $description", 'red');
            $all_valid = false;
            continue;
        }
        
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo fg("   ❌ $description JSON格式错误", 'red');
            $all_valid = false;
            continue;
        }
        
        $count = count($data);
        $total_entities += $count;
        echo fg("   ✅ $description: $count 个实体", 'green');
    }
    
    echo fg("   📊 总计: $total_entities 个白名单实体", 'cyan');
    
    return $all_valid;
}

// 显示当前状态
function showCurrentStatus() {
    $conditions = analyzeCurrentConditions();
    
    echo fg("\n📊 当前状态分析", 'cyan');
    echo fg("   时间: " . date('Y-m-d H:i:s') . " UTC", 'white');
    echo fg("   传播条件: " . $conditions['description'], 'yellow');
    echo fg("   活跃波段: " . implode(', ', $conditions['active_bands']), 'green');
    echo fg("   有利区域: " . implode(', ', $conditions['favorable_regions']), 'blue');
    
    // 显示日志统计
    if (file_exists('wsjtx_log.adi')) {
        $log_content = file_get_contents('wsjtx_log.adi');
        $qso_count = substr_count($log_content, '<EOR>');
        echo fg("   日志QSO数量: $qso_count", 'white');
    }
    
    // 显示最近的通联
    if (file_exists('dxcc_worked_cache.json')) {
        $worked = json_decode(file_get_contents('dxcc_worked_cache.json'), true);
        $worked_count = is_array($worked) ? count($worked) : 0;
        echo fg("   已通联DXCC: $worked_count 个实体", 'white');
    }
}

// 启动增强版机器人
function startEnhancedRobot() {
    echo fg("\n🚀 启动增强版机器人...", 'cyan');
    
    // 检查PHP配置
    $php_ini = PHP_OS === 'WINNT' ? 'extra\php-win.ini' : 'extra/php-lnx.ini';
    
    if (file_exists($php_ini)) {
        echo fg("   使用PHP配置文件: $php_ini", 'white');
        $command = "php -c $php_ini robot_dxcc_enhanced.php";
    } else {
        echo fg("   使用默认PHP配置", 'yellow');
        $command = "php robot_dxcc_enhanced.php";
    }
    
    echo fg("   启动命令: $command", 'gray');
    echo fg("\n" . str_repeat("=", 60), 'cyan');
    echo fg("🎯 ULTRON 智能系统启动完成！", 'bright_green');
    echo fg("📡 开始监控DXCC目标...", 'green');
    echo str_repeat("=", 60) . "\n", 'cyan';
    
    // 执行启动命令
    passthru($command);
}

// 显示使用说明
function showUsage() {
    echo fg("\n📖 使用说明:", 'cyan');
    echo fg("   1. 系统会自动分析ADI日志和当前传播条件", 'white');
    echo fg("   2. 生成智能白名单并保存到JSON文件", 'white');
    echo fg("   3. 启动增强版机器人开始自动追踪", 'white');
    echo fg("   4. 每次QSO完成后会自动更新白名单", 'white');
    echo fg("\n🛠️  命令行选项:", 'cyan');
    echo fg("   --help     显示帮助信息", 'white');
    echo fg("   --skip-wl  跳过白名单生成", 'white');
    echo fg("   --test     测试模式（不启动机器人）", 'white');
    echo fg("\n📁 相关文件:", 'cyan');
    echo fg("   dxcc_whitelist_global.json  - 全球白名单", 'gray');
    echo fg("   dxcc_whitelist_20m.json     - 20米波段白名单", 'gray');
    echo fg("   whitelist_updates.log       - 白名单更新日志", 'gray');
    echo fg("   generate_smart_whitelist.php - 智能生成器", 'gray');
}

// 主程序
function main($argv) {
    showBanner();
    
    // 解析命令行参数
    $skip_whitelist = false;
    $test_mode = false;
    $show_help = false;
    
    foreach ($argv as $arg) {
        switch ($arg) {
            case '--help':
                $show_help = true;
                break;
            case '--skip-wl':
                $skip_whitelist = true;
                break;
            case '--test':
                $test_mode = true;
                break;
        }
    }
    
    if ($show_help) {
        showUsage();
        return;
    }
    
    // 检查系统状态
    if (!checkSystemStatus()) {
        echo fg("\n❌ 系统检查失败，请修复问题后重试", 'red');
        return;
    }
    
    // 显示当前状态
    showCurrentStatus();
    
    // 生成智能白名单
    if (!$skip_whitelist) {
        if (!generateSmartWhitelist()) {
            echo fg("\n⚠️  白名单生成失败，使用现有配置继续...", 'yellow');
        }
    } else {
        echo fg("\n⏭️  跳过白名单生成", 'yellow');
    }
    
    // 验证白名单
    if (!validateWhitelists()) {
        echo fg("\n❌ 白名单验证失败", 'red');
        return;
    }
    
    // 测试模式或启动机器人
    if ($test_mode) {
        echo fg("\n🔬 测试模式完成，机器人未启动", 'yellow');
        showUsage();
    } else {
        startEnhancedRobot();
    }
}

// 运行主程序
main($argv);
?>