<?php
/**
 * 生成完整未通联DXCC白名单
 * 基于已通联缓存和完整DXCC数据库
 */

echo "=== 完整未通联DXCC白名单生成器 ===\n";
echo "将生成所有未通联实体的白名单\n\n";

// 加载已通联实体
$worked_file = 'dxcc_worked_cache.json';
if (!file_exists($worked_file)) {
    die("❌ 找不到已通联缓存文件: $worked_file\n");
}

$worked_data = json_decode(file_get_contents($worked_file), true);
$worked_ids = array_keys($worked_data);
echo "📊 已通联实体数量: " . count($worked_ids) . "\n";

// 加载完整DXCC数据库
$base_file = 'base.json';
if (!file_exists($base_file)) {
    die("❌ 找不到基础DXCC数据库: $base_file\n");
}

$base_data = json_decode(file_get_contents($base_file), true);
echo "📚 完整DXCC实体数量: " . count($base_data) . "\n";

// 找出未通联实体
$unworked_entities = array();
foreach ($base_data as $entity) {
    $dxcc_id = $entity['id'];
    if (!isset($worked_data[$dxcc_id])) {
        $unworked_entities[$dxcc_id] = array(
            'name' => $entity['name'],
            'priority' => 'medium',
            'type' => 'unworked',
            'score' => 7.0,
            'continent' => getContinent($entity['name'])
        );
    }
}

echo "🎯 未通联实体数量: " . count($unworked_entities) . "\n\n";

// 保存为全局白名单
$output_file = 'dxcc_whitelist_global.json';
if (file_put_contents($output_file, json_encode($unworked_entities, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    echo "✅ 全局白名单已更新: " . count($unworked_entities) . " 个未通联实体\n";
    echo "📁 保存为: $output_file\n";
} else {
    echo "❌ 保存失败\n";
}

// 显示统计信息
echo "\n📊 统计信息:\n";
$continent_stats = array();
foreach ($unworked_entities as $entity) {
    $continent = $entity['continent'];
    if (!isset($continent_stats[$continent])) {
        $continent_stats[$continent] = 0;
    }
    $continent_stats[$continent]++;
}

foreach ($continent_stats as $continent => $count) {
    echo "   $continent: $count 个实体\n";
}

echo "\n🚀 现在可以启动ULTRON with 完整未通联白名单了！\n";

/**
 * 获取大洲信息（复用现有函数）
 */
function getContinent($country_name) {
    $country_name = strtolower($country_name);
    
    // 南极洲
    if (strpos($country_name, 'antarctica') !== false) {
        return 'antarctica';
    }
    
    // 北美洲
    if (preg_match('/(united states|canada|mexico|alaska|hawaii|greenland|jamaica|cuba|haiti|dominican|bahamas|barbados|trinidad|panama|costa rica|guatemala|honduras|el salvador|nicaragua|belize)/', $country_name)) {
        return 'north_america';
    }
    
    // 南美洲
    if (preg_match('/(brazil|argentina|chile|peru|venezuela|colombia|ecuador|bolivia|paraguay|uruguay|guyana|suriname|falkland)/', $country_name)) {
        return 'south_america';
    }
    
    // 欧洲
    if (preg_match('/(england|france|germany|italy|spain|portugal|poland|ukraine|russia|finland|sweden|norway|denmark|netherlands|belgium|austria|switzerland|czech|slovakia|hungary|romania|bulgaria|greece|turkey|yugoslavia|bosnia|croatia|serbia|slovenia|estonia|latvia|lithuania|belarus|moldova|ireland|scotland|wales|iceland|malta|cyprus|monaco|andorra|liechtenstein|san marino|vatican|ukraine|macedonia|montenegro|kosovo)/', $country_name)) {
        return 'europe';
    }
    
    // 亚洲
    if (preg_match('/(china|japan|india|indonesia|thailand|philippines|vietnam|korea|malaysia|singapore|pakistan|bangladesh|myanmar|sri lanka|nepal|bhutan|laos|cambodia|mongolia|kazakhstan|uzbekistan|kyrgyzstan|tajikistan|turkmenistan|afghanistan|iran|iraq|israel|jordan|lebanon|syria|saudi arabia|yemen|oman|uae|qatar|kuwait|bahrain|azerbaijan|armenia|georgia|turkey|cyprus)/', $country_name)) {
        return 'asia';
    }
    
    // 非洲
    if (preg_match('/(south africa|egypt|nigeria|kenya|ethiopia|ghana|morocco|algeria|tunisia|libya|sudan|uganda|tanzania|mozambique|madagascar|cameroon|ivory coast|niger|burkina|mali|senegal|chad|guinea|rwanda|burundi|benin|togo|liberia|sierra leone|mauritania|gambia|guinea-bissau|cape verde|sao tome|comoros|seychelles|mauritius|djibouti|eritrea|somalia|zambia|zimbabwe|botswana|namibia|lesotho|eswatini|malawi|congo|gabon|central african|equatorial guinea)/', $country_name)) {
        return 'africa';
    }
    
    // 大洋洲
    if (preg_match('/(australia|new zealand|papua|fiji|vanuatu|solomon|samoa|tonga|kiribati|tuvalu|nauru|palau|marshall|micronesia|vanuatu|new caledonia|french polynesia|guam|northern mariana|american samoa|cook islands|niue|pitcairn|tokelau|wallis|futuna)/', $country_name)) {
        return 'oceania';
    }
    
    return 'unknown';
}

?>