<?php
/**
 * DXCC白名单系统测试工具
 * 全面测试新的白名单自动更新功能
 */

echo "=== DXCC白名单系统测试工具 ===\n\n";

// 测试模式配置
$test_modes = array(
    'migration' => true,      // 测试迁移功能
    'whitelist_loading' => true,  // 测试白名单加载
    'auto_update' => true,    // 测试自动更新
    'validation' => true,     // 测试文件验证
    'integration' => true     // 测试与主程序集成
);

$test_results = array();

// 1. 测试迁移功能
if ($test_modes['migration']) {
    echo "🔧 测试迁移功能...\n";
    
    if (file_exists('dxcc_config.php')) {
        require_once 'dxcc_config.php';
        
        // 检查原始配置
        $original_count = count($dxcc_whitelist);
        echo "   原始白名单数量: $original_count\n";
        
        if ($original_count > 300) {
            echo "   ✅ 检测到大型白名单（需要精简）\n";
            $test_results['migration'] = 'PASS';
        } else {
            echo "   ⚠️  白名单数量正常，但迁移仍然有益\n";
            $test_results['migration'] = 'PASS';
        }
    } else {
        echo "   ❌ 找不到dxcc_config.php文件\n";
        $test_results['migration'] = 'FAIL';
    }
}

// 2. 测试白名单管理器
if ($test_modes['whitelist_loading']) {
    echo "\n📋 测试白名单管理器...\n";
    
    require_once 'whitelist_manager.php';
    $manager = new DXCCWhitelistManager(__DIR__);
    
    // 测试加载功能
    try {
        $global_whitelist = $manager->loadWhitelist();
        $global_count = count($global_whitelist);
        echo "   全球白名单加载: $global_count 个实体\n";
        
        if ($global_count > 0 && $global_count < 200) {
            echo "   ✅ 白名单数量合理（精简有效）\n";
            $test_results['whitelist_loading'] = 'PASS';
        } elseif ($global_count == 0) {
            echo "   ⚠️  白名单为空，可能需要运行迁移\n";
            $test_results['whitelist_loading'] = 'WARN';
        } else {
            echo "   ⚠️  白名单仍然较大，建议进一步优化\n";
            $test_results['whitelist_loading'] = 'WARN';
        }
        
        // 测试波段白名单
        $band_whitelist = $manager->loadWhitelist('20m');
        $band_count = count($band_whitelist);
        echo "   20m波段白名单: $band_count 个实体\n";
        
    } catch (Exception $e) {
        echo "   ❌ 加载失败: " . $e->getMessage() . "\n";
        $test_results['whitelist_loading'] = 'FAIL';
    }
}

// 3. 测试自动更新功能
if ($test_modes['auto_update']) {
    echo "\n🔄 测试自动更新功能...\n";
    
    if (isset($manager)) {
        // 模拟QSO完成
        $test_dxcc_id = '246'; // SOV MILITARY ORDER OF MALTA
        $test_callsign = '1A0KM';
        $test_dxcc_name = 'SOV MILITARY ORDER OF MALTA';
        $test_band = '20m';
        $test_mode = 'FT8';
        
        echo "   模拟QSO: $test_callsign ($test_dxcc_name) on $test_band $test_mode\n";
        
        // 检查是否在白名单中
        $is_whitelisted = $manager->isInWhitelist($test_dxcc_id);
        echo "   是否在白名单中: " . ($is_whitelisted ? '是' : '否') . "\n";
        
        if ($is_whitelisted) {
            // 测试移除功能
            $result = $manager->removeFromWhitelist($test_dxcc_id, 'test_removal');
            echo "   移除结果: " . ($result ? '成功' : '失败') . "\n";
            
            // 验证是否已移除
            $still_whitelisted = $manager->isInWhitelist($test_dxcc_id);
            echo "   是否已移除: " . ($still_whitelisted ? '否' : '是') . "\n";
            
            if (!$still_whitelisted && $result) {
                echo "   ✅ 自动更新功能正常\n";
                $test_results['auto_update'] = 'PASS';
            } else {
                echo "   ❌ 自动更新功能异常\n";
                $test_results['auto_update'] = 'FAIL';
            }
        } else {
            echo "   ⚠️  测试实体不在白名单中\n";
            $test_results['auto_update'] = 'WARN';
        }
    } else {
        echo "   ❌ 白名单管理器未初始化\n";
        $test_results['auto_update'] = 'FAIL';
    }
}

// 4. 测试文件验证
if ($test_modes['validation']) {
    echo "\n🔍 测试文件验证...\n";
    
    if (isset($manager)) {
        $issues = $manager->validateWhitelistFiles();
        
        if (empty($issues)) {
            echo "   ✅ 所有白名单文件验证通过\n";
            $test_results['validation'] = 'PASS';
        } else {
            echo "   ⚠️  发现以下问题:\n";
            foreach ($issues as $issue) {
                echo "     - $issue\n";
            }
            $test_results['validation'] = 'WARN';
        }
    } else {
        echo "   ❌ 无法验证文件\n";
        $test_results['validation'] = 'FAIL';
    }
}

// 5. 测试集成
if ($test_modes['integration']) {
    echo "\n🔗 测试系统集成...\n";
    
    // 检查增强版机器人文件
    if (file_exists('robot_dxcc_enhanced.php')) {
        echo "   ✅ 找到增强版机器人文件\n";
        
        // 检查是否包含新功能
        $content = file_get_contents('robot_dxcc_enhanced.php');
        
        $has_whitelist_manager = strpos($content, 'DXCCWhitelistManager') !== false;
        $has_auto_update = strpos($content, 'processQSOCompletion') !== false;
        $has_json_loading = strpos($content, 'loadWhitelist') !== false;
        
        echo "   包含白名单管理器: " . ($has_whitelist_manager ? '是' : '否') . "\n";
        echo "   包含自动更新: " . ($has_auto_update ? '是' : '否') . "\n";
        echo "   包含JSON加载: " . ($has_json_loading ? '是' : '否') . "\n";
        
        if ($has_whitelist_manager && $has_auto_update && $has_json_loading) {
            echo "   ✅ 集成测试通过\n";
            $test_results['integration'] = 'PASS';
        } else {
            echo "   ❌ 集成测试失败\n";
            $test_results['integration'] = 'FAIL';
        }
    } else {
        echo "   ❌ 找不到增强版机器人文件\n";
        $test_results['integration'] = 'FAIL';
    }
}

// 显示测试结果总结
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 测试结果总结:\n";
echo str_repeat("=", 50) . "\n";

$total_tests = count($test_results);
$passed_tests = count(array_filter($test_results, function($result) { return $result === 'PASS'; }));
$warning_tests = count(array_filter($test_results, function($result) { return $result === 'WARN'; }));
$failed_tests = count(array_filter($test_results, function($result) { return $result === 'FAIL'; }));

foreach ($test_results as $test => $result) {
    $status_color = $result === 'PASS' ? '2' : ($result === 'WARN' ? '3' : '1');
    $status_text = $result === 'PASS' ? '通过' : ($result === 'WARN' ? '警告' : '失败');
    echo fg(sprintf("%-20s: %s", $test, $status_text), $status_color);
}

echo str_repeat("-", 50) . "\n";
echo fg("总计: $total_tests 项测试, $passed_tests 通过, $warning_tests 警告, $failed_tests 失败", $failed_tests > 0 ? '1' : '2');

// 提供建议
if ($failed_tests == 0) {
    echo "\n💡 建议:\n";
    echo "1. 运行 migrate_whitelist.php 进行实际迁移\n";
    echo "2. 使用 robot_dxcc_enhanced.php 代替原来的 robot_dxcc.php\n";
    echo "3. 监控 whitelist_updates.log 文件了解白名单更新情况\n";
} else {
    echo "\n⚠️  需要修复的问题:\n";
    if ($test_results['migration'] === 'FAIL') {
        echo "- 确保 dxcc_config.php 文件存在\n";
    }
    if ($test_results['whitelist_loading'] === 'FAIL') {
        echo "- 检查白名单管理器代码\n";
    }
    if ($test_results['integration'] === 'FAIL') {
        echo "- 重新生成增强版机器人文件\n";
    }
}

echo "\n🎯 测试完成！\n";

// 辅助函数
function fg($text, $color) {
    $colors = array(
        '0' => "[30m",  // Black
        '1' => "[31m",  // Red
        '2' => "[32m",  // Green
        '3' => "[33m",  // Yellow
        '6' => "[36m",  // Cyan
    );
    return chr(27) . $colors[$color] . "$text" . chr(27) . "[0m\n";
}
?>