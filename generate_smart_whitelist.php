<?php
/**
 * 智能DXCC白名单生成器
 * 
 * 基于以下因素智能生成白名单：
 * 1. ADI日志分析 - 已通联实体排除
 * 2. 稀有度评分 - 基于DXCC实体稀有程度
 * 3. 传播条件 - 当前时间和季节的传播特性
 * 4. 波段活跃度 - 不同波段的实体活跃度
 * 5. 地理分布 - 优化全球覆盖
 */

echo "=== 智能DXCC白名单生成器 ===\n";
echo "基于ADI日志和实时传播条件分析\n\n";

// 配置参数
$config = array(
    'target_count' => 100,        // 目标白名单数量
    'min_rarity_score' => 7.0,    // 最低稀有度评分
    'max_same_continent' => 15,   // 同一大洲最大数量
    'priority_bands' => ['20m', '17m', '15m'], // 优先波段
    'seasonal_boost' => getSeasonalBoost(),    // 季节性增强
);

// 加载基础数据
echo "📚 加载基础数据...\n";
$base_data = loadBaseData();
$worked_entities = analyzeADILog();
$current_conditions = analyzeCurrentConditions();

echo "   基础数据: " . count($base_data) . " 个DXCC实体\n";
echo "   已通联: " . count($worked_entities) . " 个实体\n";
echo "   传播条件: " . $current_conditions['description'] . "\n\n";

// 计算稀有度评分
echo "🎯 计算稀有度评分...\n";
$scored_entities = calculateRarityScores($base_data, $worked_entities, $current_conditions);
echo "   评分完成: " . count($scored_entities) . " 个实体\n\n";

// 智能选择白名单
echo "🧠 智能选择白名单...\n";
$smart_whitelist = selectSmartWhitelist($scored_entities, $config);
echo "   选择完成: " . count($smart_whitelist) . " 个实体\n\n";

// 按波段优化
echo "📡 按波段优化...\n";
$band_whitelists = optimizeByBands($smart_whitelist, $base_data, $config);
echo "   波段优化完成\n\n";

// 生成报告
echo "📊 生成分析报告...\n";
generateReport($smart_whitelist, $band_whitelists, $worked_entities, $current_conditions);

// 保存白名单
saveWhitelists($smart_whitelist, $band_whitelists);

echo "\n✅ 智能白名单生成完成！\n";
echo "🚀 现在可以启动: php robot_dxcc_enhanced.php\n";

/**
 * 加载基础数据
 */
function loadBaseData() {
    if (!file_exists('base.json')) {
        die("❌ 找不到base.json文件\n");
    }
    
    $json = file_get_contents('base.json');
    $data = json_decode($json, true);
    
    if (!is_array($data)) {
        die("❌ base.json格式错误\n");
    }
    
    return $data;
}

/**
 * 分析ADI日志
 */
function analyzeADILog() {
    $worked = array();
    $log_file = 'wsjtx_log.adi';
    
    if (!file_exists($log_file)) {
        echo "⚠️  找不到ADI日志文件，将使用空日志分析\n";
        return $worked;
    }
    
    $content = file_get_contents($log_file);
    if (empty($content)) {
        return $worked;
    }
    
    // 解析ADIF格式
    preg_match_all('/<CALL:(\d+)>([^<]+).*?<DXCC:(\d+)>([^<]+)/', $content, $matches);
    
    for ($i = 0; $i < count($matches[0]); $i++) {
        $callsign = trim($matches[2][$i]);
        $dxcc_id = trim($matches[3][$i]);
        $country = trim($matches[4][$i]);
        
        $worked[$dxcc_id] = array(
            'callsign' => $callsign,
            'country' => $country,
            'date' => date('Y-m-d'), // 简化处理
            'band' => null,
            'mode' => null
        );
    }
    
    return $worked;
}

/**
 * 分析当前传播条件
 */
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
        // 白天
        $conditions['active_bands'] = ['15m', '12m', '10m', '6m'];
        $conditions['description'] = '白天 - 高波段活跃';
    } elseif ($hour >= 20 || $hour <= 4) {
        // 夜间
        $conditions['active_bands'] = ['160m', '80m', '40m', '30m'];
        $conditions['description'] = '夜间 - 低波段活跃';
    } else {
        // 黄昏/黎明
        $conditions['active_bands'] = ['20m', '17m', '15m', '40m'];
        $conditions['description'] = '黄昏 - 多波段开放';
    }
    
    // 根据季节确定有利区域
    switch ($season) {
        case 'spring':
            $conditions['favorable_regions'] = ['south_america', 'africa', 'oceania'];
            break;
        case 'summer':
            $conditions['favorable_regions'] = ['europe', 'asia', 'africa'];
            break;
        case 'autumn':
            $conditions['favorable_regions'] = ['north_america', 'asia', 'oceania'];
            break;
        case 'winter':
            $conditions['favorable_regions'] = ['south_america', 'africa', 'antarctica'];
            break;
    }
    
    return $conditions;
}

/**
 * 计算稀有度评分
 */
function calculateRarityScores($base_data, $worked_entities, $conditions) {
    $scored = array();
    
    foreach ($base_data as $entity) {
        $dxcc_id = $entity['id'];
        $name = $entity['name'];
        $continent = getContinent($name);
        
        // 基础稀有度评分 (1-10)
        $base_score = calculateBaseRarityScore($name, $continent);
        
        // 已通联惩罚
        $worked_penalty = isset($worked_entities[$dxcc_id]) ? -5.0 : 0.0;
        
        // 传播条件加成
        $propagation_bonus = 0.0;
        if (in_array($continent, $conditions['favorable_regions'])) {
            $propagation_bonus += 1.5;
        }
        
        // 时间敏感性加成
        $time_bonus = calculateTimeSensitivity($name);
        
        // 最终评分
        $final_score = $base_score + $worked_penalty + $propagation_bonus + $time_bonus;
        $final_score = max(0, min(10, $final_score)); // 限制在0-10范围内
        
        $scored[$dxcc_id] = array(
            'name' => $name,
            'continent' => $continent,
            'base_score' => $base_score,
            'worked_penalty' => $worked_penalty,
            'propagation_bonus' => $propagation_bonus,
            'time_bonus' => $time_bonus,
            'final_score' => $final_score,
            'worked' => isset($worked_entities[$dxcc_id])
        );
    }
    
    // 按评分排序
    uasort($scored, function($a, $b) {
        return $b['final_score'] <=> $a['final_score'];
    });
    
    return $scored;
}

/**
 * 智能选择白名单
 */
function selectSmartWhitelist($scored_entities, $config) {
    $selected = array();
    $continent_count = array();
    $count = 0;
    
    foreach ($scored_entities as $dxcc_id => $data) {
        // 跳过已通联的
        if ($data['worked']) continue;
        
        // 跳过评分太低的
        if ($data['final_score'] < $config['min_rarity_score']) continue;
        
        // 大洲数量限制
        $continent = $data['continent'];
        if (isset($continent_count[$continent]) && 
            $continent_count[$continent] >= $config['max_same_continent']) {
            continue;
        }
        
        // 选择该实体
        $selected[$dxcc_id] = $data;
        
        // 更新计数
        $count++;
        if (!isset($continent_count[$continent])) {
            $continent_count[$continent] = 0;
        }
        $continent_count[$continent]++;
        
        // 达到目标数量
        if ($count >= $config['target_count']) {
            break;
        }
    }
    
    return $selected;
}

/**
 * 按波段优化
 */
function optimizeByBands($smart_whitelist, $base_data, $config) {
    $band_whitelists = array();
    $all_bands = ['160m', '80m', '40m', '30m', '20m', '17m', '15m', '12m', '10m', '6m'];
    
    foreach ($all_bands as $band) {
        $band_whitelists[$band] = array();
        
        // 基础：全球白名单
        foreach ($smart_whitelist as $dxcc_id => $data) {
            $band_whitelists[$band][$dxcc_id] = $data;
        }
        
        // 波段特定增强
        $band_additions = getBandSpecificAdditions($band, $base_data);
        foreach ($band_additions as $dxcc_id => $name) {
            if (!isset($band_whitelists[$band][$dxcc_id])) {
                $band_whitelists[$band][$dxcc_id] = array(
                    'name' => $name,
                    'final_score' => 6.0, // 中等评分
                    'continent' => getContinent($name),
                    'band_specific' => true
                );
            }
        }
    }
    
    return $band_whitelists;
}

/**
 * 生成分析报告
 */
function generateReport($smart_whitelist, $band_whitelists, $worked_entities, $conditions) {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "📊 智能白名单分析报告\n";
    echo str_repeat("=", 60) . "\n\n";
    
    // 基础统计
    echo "🎯 基础统计:\n";
    echo "   全球白名单: " . count($smart_whitelist) . " 个实体\n";
    echo "   已通联实体: " . count($worked_entities) . " 个\n";
    echo "   传播条件: " . $conditions['description'] . "\n";
    echo "   活跃波段: " . implode(', ', $conditions['active_bands']) . "\n";
    echo "   有利区域: " . implode(', ', $conditions['favorable_regions']) . "\n\n";
    
    // 地理分布
    echo "🌍 地理分布:\n";
    $continent_stats = array();
    foreach ($smart_whitelist as $data) {
        $continent = $data['continent'];
        if (!isset($continent_stats[$continent])) {
            $continent_stats[$continent] = 0;
        }
        $continent_stats[$continent]++;
    }
    
    foreach ($continent_stats as $continent => $count) {
        echo "   $continent: $count 个实体\n";
    }
    echo "\n";
    
    // 评分分布
    echo "📈 评分分布:\n";
    $score_ranges = array(
        '9-10分' => 0,
        '8-9分' => 0,
        '7-8分' => 0,
        '6-7分' => 0,
        '5-6分' => 0
    );
    
    foreach ($smart_whitelist as $data) {
        $score = $data['final_score'];
        if ($score >= 9) $score_ranges['9-10分']++;
        elseif ($score >= 8) $score_ranges['8-9分']++;
        elseif ($score >= 7) $score_ranges['7-8分']++;
        elseif ($score >= 6) $score_ranges['6-7分']++;
        else $score_ranges['5-6分']++;
    }
    
    foreach ($score_ranges as $range => $count) {
        if ($count > 0) {
            echo "   $range: $count 个实体\n";
        }
    }
    echo "\n";
    
    // 波段统计
    echo "📡 波段白名单统计:\n";
    foreach ($band_whitelists as $band => $whitelist) {
        $band_specific = count(array_filter($whitelist, function($d) { return isset($d['band_specific']); }));
        echo "   $band: " . count($whitelist) . " 个实体 (波段特定: $band_specific)\n";
    }
    echo "\n";
    
    // 推荐目标（前10个）
    echo "🎯 推荐目标（前10个）:\n";
    $top_entities = array_slice($smart_whitelist, 0, 10, true);
    $counter = 1;
    foreach ($top_entities as $dxcc_id => $data) {
        echo sprintf("   %2d. %-40s 评分: %.1f 大洲: %s\n", 
            $counter++, 
            $data['name'], 
            $data['final_score'], 
            $data['continent']
        );
    }
}

/**
 * 保存白名单文件
 */
function saveWhitelists($smart_whitelist, $band_whitelists) {
    echo "\n💾 保存白名单文件...\n";
    
    // 准备全球白名单数据
    $global_data = array();
    foreach ($smart_whitelist as $dxcc_id => $data) {
        $global_data[$dxcc_id] = array(
            'name' => $data['name'],
            'priority' => $data['final_score'] >= 8 ? 'high' : 'medium',
            'type' => 'smart_selected',
            'score' => $data['final_score'],
            'continent' => $data['continent']
        );
    }
    
    // 保存全球白名单
    if (file_put_contents('dxcc_whitelist_global.json', 
        json_encode($global_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo "   ✅ 全球白名单已保存: " . count($global_data) . " 个实体\n";
    }
    
    // 保存波段白名单
    foreach ($band_whitelists as $band => $whitelist) {
        $band_data = array();
        foreach ($whitelist as $dxcc_id => $data) {
            $band_data[$dxcc_id] = array(
                'name' => $data['name'],
                'priority' => $data['final_score'] >= 8 ? 'high' : 'medium',
                'type' => isset($data['band_specific']) ? 'band_specific' : 'global',
                'score' => $data['final_score'],
                'continent' => $data['continent']
            );
        }
        
        $filename = "dxcc_whitelist_$band.json";
        if (file_put_contents($filename, 
            json_encode($band_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            echo "   ✅ $band 波段白名单已保存: " . count($band_data) . " 个实体\n";
        }
    }
}

/**
 * 辅助函数：获取季节
 */
function getSeason($month) {
    if ($month >= 3 && $month <= 5) return 'spring';
    if ($month >= 6 && $month <= 8) return 'summer';
    if ($month >= 9 && $month <= 11) return 'autumn';
    return 'winter';
}

/**
 * 辅助函数：获取季节性增强
 */
function getSeasonalBoost() {
    $month = date('n');
    $season = getSeason($month);
    
    $boosts = array(
        'spring' => array('south_america' => 1.2, 'africa' => 1.1, 'oceania' => 1.3),
        'summer' => array('europe' => 1.1, 'asia' => 1.2, 'africa' => 1.1),
        'autumn' => array('north_america' => 1.2, 'asia' => 1.1, 'oceania' => 1.2),
        'winter' => array('south_america' => 1.3, 'africa' => 1.2, 'antarctica' => 1.5)
    );
    
    return $boosts[$season];
}

/**
 * 辅助函数：获取大洲
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

/**
 * 计算基础稀有度评分
 */
function calculateBaseRarityScore($country_name, $continent) {
    $score = 5.0; // 基础评分
    
    // 特殊稀有实体
    $rare_entities = array(
        'SOV MILITARY ORDER OF MALTA' => 10.0,
        'BOUVET' => 10.0,
        'PETER I IS' => 10.0,
        'RODRIGUEZ IS' => 9.5,
        'TRISTAN DA CUNHA' => 9.5,
        'PRINCE EDWARD IS' => 9.5,
        'ANNOBON I.' => 9.0,
        'SOUTH SHETLAND ISLANDS' => 9.0,
        'SOUTH ORKNEY ISLANDS' => 9.0,
        'SOUTH SANDWICH ISLANDS' => 9.0,
        'SOUTH GEORGIA ISLAND' => 9.0,
        'FALKLAND ISLANDS' => 8.5,
        'SAO TOME IS' => 8.5,
        'COMOROS' => 8.0,
        'DJIBOUTI' => 8.0,
        'EQUATORIAL GUINEA' => 8.0,
        'GABON' => 8.0,
        'GUINEA-BISSAU' => 8.0,
        'IVORY COAST' => 8.0,
        'LIBERIA' => 8.0,
        'MAURITANIA' => 8.0,
        'NIGER' => 8.0,
        'SIERRA LEONE' => 8.0,
        'TOGO' => 8.0,
        'CENTRAL AFRICAN REPUBLIC' => 8.0,
        'CHAD' => 8.0,
        'CONGO' => 8.0,
        'ZAIRE' => 8.0
    );
    
    // 检查是否在稀有实体列表中
    foreach ($rare_entities as $entity => $rarity_score) {
        if (stripos($country_name, $entity) !== false) {
            return $rarity_score;
        }
    }
    
    // 基于大洲调整
    $continent_scores = array(
        'antarctica' => 9.0,
        'oceania' => 7.5,
        'africa' => 7.0,
        'south_america' => 6.5,
        'asia' => 6.0,
        'europe' => 5.5,
        'north_america' => 5.0
    );
    
    if (isset($continent_scores[$continent])) {
        $score = $continent_scores[$continent];
    }
    
    // 特殊关键词调整
    if (stripos($country_name, 'ISLAND') !== false) {
        $score += 1.0;
    }
    if (stripos($country_name, 'ATOLL') !== false) {
        $score += 1.5;
    }
    if (stripos($country_name, 'REEF') !== false) {
        $score += 1.0;
    }
    
    return min(10, $score);
}

/**
 * 计算时间敏感性
 */
function calculateTimeSensitivity($country_name) {
    $bonus = 0.0;
    
    // 极地地区冬季加成
    if (stripos($country_name, 'ANTARCTICA') !== false) {
        $month = date('n');
        if ($month >= 5 && $month <= 9) {
            $bonus += 2.0; // 南极冬季
        }
    }
    
    // 高纬度地区冬季加成
    $arctic_regions = array('GREENLAND', 'SVALBARD', 'ICELAND', 'NORTH POLE');
    foreach ($arctic_regions as $region) {
        if (stripos($country_name, $region) !== false) {
            $month = date('n');
            if ($month >= 11 || $month <= 3) {
                $bonus += 1.0; // 北极冬季
            }
        }
    }
    
    return $bonus;
}

/**
 * 获取波段特定添加项
 */
function getBandSpecificAdditions($band, $base_data) {
    $additions = array();
    
    // 基于波段特性添加特定实体
    $band_entities = array(
        '10m' => array('339', '155', '237'), // 日本、瑙鲁、夏威夷
        '15m' => array('436', '339', '155'), // 利比亚、日本、瑙鲁  
        '20m' => array('436', '339', '155'), // 利比亚、日本、瑙鲁
        '40m' => array('436', '339', '155'), // 利比亚、日本、瑙鲁
        '80m' => array('247', '260', '4'),   // 梵蒂冈、摩纳哥、阿富汗
        '160m' => array('247', '260', '4')   // 梵蒂冈、摩纳哥、阿富汗
    );
    
    if (isset($band_entities[$band])) {
        foreach ($band_entities[$band] as $dxcc_id) {
            foreach ($base_data as $entity) {
                if ($entity['id'] == $dxcc_id) {
                    $additions[$dxcc_id] = $entity['name'];
                    break;
                }
            }
        }
    }
    
    return $additions;
}

?>