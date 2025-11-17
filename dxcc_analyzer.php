<?php
// DXCC分析脚本
// 用于分析日志文件，识别已通联和未通联的DXCC实体

echo "正在分析DXCC通联情况...\n";

// 加载呼号数据库
$resultados_json = file_get_contents('base.json');
$base = json_decode($resultados_json, true);

if (!$base) {
    die("无法加载base.json文件\n");
}

// 加载通联日志
$log_file = 'wsjtx_log.adi';
if (!file_exists($log_file)) {
    die("无法找到日志文件: $log_file\n");
}

$worked_dxcc = array();      // 所有已通联的DXCC
$worked_dxcc_by_band = array(); // 按波段记录的已通联DXCC

$contents = file_get_contents($log_file);
$qsos = explode('<eor>', $contents);

foreach ($qsos as $qso) {
    if (strpos($qso, '<call:') !== false) {
        // 解析QSO记录
        preg_match('/<call:([0-9]+)>(\w+)/', $qso, $call_match);
        preg_match('/<band:([0-9]+)>(\w+)/', $qso, $band_match);
        
        if (isset($call_match[2])) {
            $call = strtoupper($call_match[2]);
            
            // 根据呼号查找DXCC信息
            $dxcc_info = locate($call, $base);
            if ($dxcc_info && $dxcc_info['id'] != 'unknown') {
                $dxcc_id = $dxcc_info['id'];
                $worked_dxcc[$dxcc_id] = $dxcc_info['name'];
                
                // 如果有波段信息，记录按波段的通联
                $band = 'unknown';
                if (isset($band_match[2])) {
                    $band = $band_match[2];
                }
                
                if (!isset($worked_dxcc_by_band[$band])) {
                    $worked_dxcc_by_band[$band] = array();
                }
                $worked_dxcc_by_band[$band][$dxcc_id] = $dxcc_info['name'];
            }
        }
    }
}

echo "\n=== 已通联的DXCC实体 ===\n";
echo "总共通联了 " . count($worked_dxcc) . " 个DXCC实体\n\n";

foreach ($worked_dxcc as $id => $name) {
    echo "$id: $name\n";
}

echo "\n=== 按波段的通联情况 ===\n";
foreach ($worked_dxcc_by_band as $band => $dxcc_list) {
    echo "\n$band 波段: " . count($dxcc_list) . " 个DXCC实体\n";
    foreach ($dxcc_list as $id => $name) {
        echo "  $id: $name\n";
    }
}

// 计算未通联的DXCC实体
$all_dxcc = array();
foreach ($base as $entry) {
    $all_dxcc[$entry['id']] = $entry['name'];
}

$unworked_dxcc = array_diff_key($all_dxcc, $worked_dxcc);

echo "\n=== 未通联的DXCC实体 ===\n";
echo "还有 " . count($unworked_dxcc) . " 个DXCC实体未通联\n\n";

// 显示前20个未通联的DXCC实体
$counter = 0;
foreach ($unworked_dxcc as $id => $name) {
    if ($counter < 20) {
        echo "$id: $name\n";
        $counter++;
    } else {
        echo "... 还有 " . (count($unworked_dxcc) - $counter) . " 个未显示\n";
        break;
    }
}

// 建议的白名单 - 随机选择一些未通联的DXCC实体
$recommended_whitelist = array_slice(array_keys($unworked_dxcc), 0, 10);
echo "\n=== 推荐的DXCC白名单 ===\n";
echo "// 在robot_dxcc.php中设置这些DXCC实体\n";
echo "// \$dxcc_whitelist = array(";
echo implode(', ', array_map(function($id) { return "\"$id\""; }, $recommended_whitelist));
echo ");\n";

// 按波段建议
echo "\n=== 按波段的通联建议 ===\n";
foreach ($worked_dxcc_by_band as $band => $dxcc_list) {
    $unworked_in_band = array_diff_key($all_dxcc, $dxcc_list);
    echo "\n$band 波段: ";
    if (count($unworked_in_band) > 0) {
        $band_recommend = array_slice(array_keys($unworked_in_band), 0, 5);
        echo "推荐白名单: ";
        echo implode(', ', array_map(function($id) { return "\"$id\""; }, $band_recommend));
        echo "\n  // \$band_whitelist['$band'] = array(";
        echo implode(', ', array_map(function($id) { return "\"$id\""; }, $band_recommend));
        echo ");";
    } else {
        echo "所有DXCC实体都已通联！";
    }
}

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

echo "\n分析完成！\n";
echo "\n要使用DXCC白名单功能:\n";
echo "1. 编辑 dxcc_config.php 文件，设置您想要优先通联的DXCC实体\n";
echo "2. 将配置应用到 robot_dxcc.php 中\n";
echo "3. 运行增强版ULTRON: php -c extra/php-lnx.ini robot_dxcc.php\n";
?>