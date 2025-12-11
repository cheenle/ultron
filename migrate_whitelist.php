<?php
/**
 * DXCC白名单迁移工具
 * 将当前的PHP配置文件转换为新的JSON格式
 * 并提供智能精简功能
 */

echo "=== DXCC白名单迁移工具 ===\n\n";

// 加载当前配置
if (!file_exists('dxcc_config.php')) {
    die("错误: 找不到dxcc_config.php文件\n");
}

require_once 'dxcc_config.php';

// 加载已通联的DXCC列表
$worked_dxcc = array();
$log_file = 'wsjtx_log.adi';
if (file_exists($log_file)) {
    echo "正在分析日志文件...\n";
    $contents = file_get_contents($log_file);
    $qsos = explode('<eor>', $contents);
    
    // 加载呼号数据库
    if (file_exists('base.json')) {
        $resultados_json = file_get_contents('base.json');
        $base = json_decode($resultados_json, true);
        
        foreach ($qsos as $qso) {
            if (strpos($qso, '<call:') !== false) {
                preg_match('/<call:([0-9]+)>(\w+)/', $qso, $call_match);
                if (isset($call_match[2])) {
                    $call = strtoupper($call_match[2]);
                    $dxcc_info = locate($call, $base);
                    if ($dxcc_info && $dxcc_info['id'] != 'unknown') {
                        $worked_dxcc[$dxcc_info['id']] = $dxcc_info['name'];
                    }
                }
            }
        }
    }
}

echo "已通联DXCC实体数量: " . count($worked_dxcc) . "\n";

// 精简白名单 - 移除已通联的实体和常见实体
$global_whitelist = array();
$common_entities = array('291', '110', '27', '503', '61', '170', '195', '130', '225', '184'); // 常见实体ID

foreach ($dxcc_whitelist as $id => $name) {
    // 只保留未通联的实体，并排除一些常见实体
    if (!isset($worked_dxcc[$id]) && !in_array($id, $common_entities)) {
        $global_whitelist[$id] = array(
            'name' => $name,
            'priority' => 'high',
            'type' => 'rare'
        );
    }
}

// 如果精简后太少，补充一些重要实体
echo "精简后全球白名单数量: " . count($global_whitelist) . "\n";

if (count($global_whitelist) < 50) {
    echo "警告: 白名单实体过少，建议手动添加一些目标实体\n";
}

// 创建波段白名单（基于全球白名单 + 该波段活跃实体）
$band_whitelists = array();
$bands = array('160m', '80m', '40m', '30m', '20m', '17m', '15m', '12m', '10m', '6m');

foreach ($bands as $band) {
    $band_whitelists[$band] = array();
    
    // 基础: 全球白名单
    foreach ($global_whitelist as $id => $data) {
        $band_whitelists[$band][$id] = $data;
    }
    
    // 添加一些波段特定的实体（这里简化处理，实际可以根据波段传播特性）
    $band_additions = array(
        '10m' => array('339', '155', '237'), // 10m活跃实体
        '20m' => array('436', '339', '155'), // 20m活跃实体
        '40m' => array('436', '339', '155'), // 40m活跃实体
        '80m' => array('436', '339', '155'), // 80m活跃实体
    );
    
    if (isset($band_additions[$band])) {
        foreach ($band_additions[$band] as $id) {
            if (isset($dxcc_whitelist[$id]) && !isset($worked_dxcc[$id])) {
                $band_whitelists[$band][$id] = array(
                    'name' => $dxcc_whitelist[$id],
                    'priority' => 'medium',
                    'type' => 'band_specific'
                );
            }
        }
    }
}

// 保存到JSON文件
$files_created = array();

// 全球白名单
if (file_put_contents('dxcc_whitelist_global.json', json_encode($global_whitelist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    $files_created[] = 'dxcc_whitelist_global.json';
}

// 波段白名单
foreach ($band_whitelists as $band => $whitelist) {
    $filename = "dxcc_whitelist_{$band}.json";
    if (file_put_contents($filename, json_encode($whitelist, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        $files_created[] = $filename;
    }
}

// 已通联实体缓存
if (file_put_contents('dxcc_worked_cache.json', json_encode($worked_dxcc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    $files_created[] = 'dxcc_worked_cache.json';
}

// 迁移配置
$migration_config = array(
    'version' => '2.0',
    'created_date' => date('Y-m-d H:i:s'),
    'original_config' => 'dxcc_config.php',
    'whitelist_mode' => $dxcc_whitelist_only ? 'whitelist_only' : 'prefer_whitelist',
    'files' => $files_created,
    'stats' => array(
        'original_count' => count($dxcc_whitelist),
        'worked_count' => count($worked_dxcc),
        'global_count' => count($global_whitelist),
        'bands_configured' => count($band_whitelists)
    )
);

file_put_contents('dxcc_whitelist_config.json', json_encode($migration_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n=== 迁移完成 ===\n";
echo "创建的文件:\n";
foreach ($files_created as $file) {
    echo "  - $file\n";
}
echo "\n统计信息:\n";
echo "  - 原始白名单: " . count($dxcc_whitelist) . " 个实体\n";
echo "  - 已通联实体: " . count($worked_dxcc) . " 个\n";
echo "  - 精简后全球白名单: " . count($global_whitelist) . " 个实体\n";
echo "  - 波段白名单: " . count($band_whitelists) . " 个波段\n";

echo "\n=== 下一步操作 ===\n";
echo "1. 检查生成的JSON文件\n";
echo "2. 更新robot_dxcc.php以使用新的白名单系统\n";
echo "3. 测试新的自动更新功能\n";

function locate($licrx, $base)
{
    $z = strlen($licrx);
    $licrx = str_replace(['\\', '/'], ['\\\\', '\\/'], $licrx);
    for ($i = $z; $i >= 1; $i--) {
        $licencia_recortada = substr($licrx, 0, $i);
        foreach ($base as $resultado) {
            $expresion_regular = '/\b ' . $licencia_recortada . '\b/';
            if (preg_match($expresion_regular, $resultado['licencia'])) {
                return array(
                    'id' => $resultado['id'],
                    'flag' => $resultado['flag'],
                    'name' => $resultado['name']
                );
            }
        }
    }
    return array(
        'id' => 'unknown',
        'flag' => 'unknown',
        'name' => 'unknown'
    );
}
?>