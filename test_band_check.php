<?php
error_reporting(0);
date_default_timezone_set("UTC");

// 模拟配置文件中的变量
$dxcc_whitelist_only = 1;
$dxcc_whitelist = array();
$band_whitelist = array();

// 只加载必要的函数和数据
$resultados_json = file_get_contents('base.json');
$base = json_decode($resultados_json, true);

function locate($licrx) {
    global $base;
    $z = strlen($licrx);
    $licrx = str_replace(['\\', '/'], ['\\\\', '\\/'], $licrx);
    for ($i = $z; $i >= 1; $i--) {
        $licencia_recortada = substr($licrx, 0, $i);
        foreach ($base as $resultado) {
            $licencia_data = trim($resultado['licencia']);
            $parts = explode(' ', $licencia_data);
            if (count($parts) > 1) {
                $prefixes = array_slice($parts, 1);
                foreach ($prefixes as $prefix) {
                    $clean_prefix = preg_replace('/[\/(].*$/', '', $prefix);
                    if ($clean_prefix === $licencia_recortada) {
                        return array(
                            'id' => $resultado['id'],
                            'flag' => $resultado['flag'],
                            'name' => $resultado['name']
                        );
                    }
                }
            }
        }
    }
    return array('id' => 'unknown', 'flag' => 'unknown', 'name' => 'unknown');
}

// 分析日志文件
$filename = 'wsjtx_log.adi';
$worked_dxcc = array();
$worked_dxcc_bands = array();

$archivoEntrada = fopen($filename, 'r');
while (($linea = fgets($archivoEntrada)) !== false) {
    if (strpos($linea, '<eor>') !== false || strpos($linea, '<EOR>') !== false) {
        // 简单的ADIF解析
        preg_match('/<call:(<\d+>)>([^<]+)/', $linea, $call_match);
        preg_match('/<band:(<\d+>)>([^<]+)/', $linea, $band_match);
        
        if (isset($call_match[2])) {
            $call = trim($call_match[2]);
            $band = isset($band_match[2]) ? trim($band_match[2]) : '';
            
            $dxcc_info = locate($call);
            if ($dxcc_info && isset($dxcc_info['id'])) {
                $dxcc_id = $dxcc_info['id'];
                $worked_dxcc[$dxcc_id] = $dxcc_info['name'];
                
                if ($band) {
                    if (!isset($worked_dxcc_bands[$band])) {
                        $worked_dxcc_bands[$band] = array();
                    }
                    $worked_dxcc_bands[$band][$dxcc_id] = $dxcc_info['name'];
                }
            }
        }
    }
}
fclose($archivoEntrada);

echo "=== 全局通联情况 ===\n";
if (isset($worked_dxcc['339'])) {
    echo "日本 (ID: 339) 已通联: " . $worked_dxcc['339'] . "\n";
} else {
    echo "日本 (ID: 339) 未通联\n";
}

echo "\n=== 按波段通联情况 ===\n";
foreach (['160m', '80m', '40m', '30m', '20m', '17m', '15m', '12m', '10m', '6m'] as $test_band) {
    if (isset($worked_dxcc_bands[$test_band]['339'])) {
        echo "$test_band 波段: 日本已通联\n";
    } else {
        echo "$test_band 波段: 日本未通联\n";
    }
}

echo "\n=== 日本电台通联记录示例 ===\n";
$archivoEntrada = fopen($filename, 'r');
$count = 0;
while (($linea = fgets($archivoEntrada)) !== false && $count < 10) {
    if (strpos($linea, '<eor>') !== false || strpos($linea, '<EOR>') !== false) {
        preg_match('/<call:(<\d+>)>([^<]+)/', $linea, $call_match);
        preg_match('/<band:(<\d+>)>([^<]+)/', $linea, $band_match);
        
        if (isset($call_match[2])) {
            $call = trim($call_match[2]);
            if (preg_match('/^J[A-Z]/', $call)) { // 日本电台
                $band = isset($band_match[2]) ? trim($band_match[2]) : '未知';
                echo "$call - $band\n";
                $count++;
            }
        }
    }
}
fclose($archivoEntrada);
?>