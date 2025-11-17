<?php
// 检查白名单中已通联的实体

echo "=== 白名单 vs 已通联实体对比分析 ===\n\n";

// 加载已通联的DXCC列表
$worked_dxcc = array();
$log_file = 'wsjtx_log.adi';
if (file_exists($log_file)) {
    $contents = file_get_contents($log_file);
    $qsos = explode('<eor>', $contents);
    
    // 加载呼号数据库
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

// 加载白名单配置
require_once 'dxcc_config.php';

echo "已通联DXCC实体数量: " . count($worked_dxcc) . "\n";
echo "白名单DXCC实体数量: " . count($dxcc_whitelist) . "\n\n";

// 查找白名单中已通联的实体
$worked_in_whitelist = array();
foreach ($dxcc_whitelist as $id => $name) {
    if (isset($worked_dxcc[$id])) {
        $worked_in_whitelist[$id] = $name;
    }
}

echo "=== 白名单中已通联的实体（需要从白名单移除）===\n";
echo "数量: " . count($worked_in_whitelist) . "\n\n";

foreach ($worked_in_whitelist as $id => $name) {
    echo "$id: $name\n";
}

// 查找真正未通联的实体
$truly_unworked = array();
foreach ($dxcc_whitelist as $id => $name) {
    if (!isset($worked_dxcc[$id])) {
        $truly_unworked[$id] = $name;
    }
}

echo "\n=== 真正未通联的实体（应该保留在白名单中）===\n";
echo "数量: " . count($truly_unworked) . "\n\n";

$counter = 0;
foreach ($truly_unworked as $id => $name) {
    echo "$id: $name\n";
    $counter++;
    if ($counter >= 20) {
        echo "... 还有 " . (count($truly_unworked) - $counter) . " 个未显示\n";
        break;
    }
}

echo "\n=== 建议更新后的白名单配置 ===\n";
echo "// 更新后的白名单（移除已通联的实体）\n";
echo "\$dxcc_whitelist = array(\n";
$counter = 0;
foreach ($truly_unworked as $id => $name) {
    echo "    \"$id\" => \"$name\",";
    if ($counter % 2 == 1) echo "\n";
    $counter++;
}
echo ");\n";

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

echo "\n分析完成！建议更新dxcc_config.php文件。\n";
?>