<?php
error_reporting(0);
date_default_timezone_set("UTC");

// 模拟配置文件中的变量
$dxcc_whitelist_only = 1;
$dxcc_whitelist = array(
    "246" => "SOV MILITARY ORDER OF MALTA",
    "260" => "MONACO", 
    "207" => "RODRIGUEZ IS",
    "49" => "EQUATORIAL GUINEA",
    "195" => "ANNOBON I.",
    "474" => "TUNISIA",
    "107" => "GUINEA",
    "24" => "BOUVET",
    "199" => "PETER I IS"
);
$band_whitelist = array();

// 只加载必要的函数和数据
$resultados_json = file_get_contents('base.json');
$base = json_decode($resultados_json, true);

function locate($licrx)
{
    global $base;
    $z = strlen($licrx);
    $licrx = str_replace(['\\', '/'], ['\\\\', '\\/'], $licrx);
    for ($i = $z; $i >= 1; $i--) {
        $licencia_recortada = substr($licrx, 0, $i);
        foreach ($base as $resultado) {
            // 获取国家名称和前缀列表
            $licencia_data = trim($resultado['licencia']);
            // 分割国家名称和前缀（第一部分是国家名称）
            $parts = explode(' ', $licencia_data);
            if (count($parts) > 1) {
                // 跳过第一个元素（国家名称），从第二个开始是实际前缀
                $prefixes = array_slice($parts, 1);
                foreach ($prefixes as $prefix) {
                    // 清理前缀，移除可能的附加信息（如 /L, /6 等）
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
    return array(
        'id' => 'unknown',
        'flag' => 'unknown',
        'name' => 'unknown'
    );
}

$test_calls = ["JG3LGD", "JA0CZC", "JH0MAU", "JG5VFK", "BG1SB", "JA1GLE", "BH1WVM"];
foreach ($test_calls as $call) {
    $result = locate($call);
    echo "呼叫: $call => DXCC ID: {$result['id']}, 名称: {$result['name']}\n";
}

echo "\n检查日本是否在白名单中:\n";
if (isset($dxcc_whitelist['339'])) {
    echo "日本 (ID: 339) 在全局白名单中: " . $dxcc_whitelist['339'] . "\n";
} else {
    echo "日本 (ID: 339) 不在全局白名单中\n";
}

echo "\n波段白名单检查:\n";
if (isset($band_whitelist['40m'])) {
    echo "40m波段白名单包含 " . count($band_whitelist['40m']) . " 个实体\n";
    if (in_array('339', $band_whitelist['40m'])) {
        echo "日本 (ID: 339) 在40m波段白名单中\n";
    } else {
        echo "日本 (ID: 339) 不在40m波段白名单中\n";
    }
} else {
    echo "40m波段白名单未设置\n";
}
?>