<?php
/**
 * Ollama DXCC系统测试文件
 * 用于测试AI增强的DXCC决策系统
 */

require_once 'ollama_dxcc_analyzer.php';
require_once 'ollama_dxcc_prompt_engineering.php';
require_once 'ollama_dxcc_decision_maker.php';
require_once 'whitelist_manager.php';

echo "🔍 Ollama DXCC AI系统测试\n";
echo str_repeat("=", 50) . "\n";

// 初始化白名单管理器
$whitelist_manager = new DXCCWhitelistManager(__DIR__);

// 检查Ollama服务是否可用
$analyzer = new OllamaDXCCAnalyzer();
$available = $analyzer->isAvailable();

echo "Ollama服务状态: " . ($available ? "✅ 可用" : "❌ 不可用") . "\n";

if (!$available) {
    echo "⚠️  无法连接到Ollama服务，这在没有启动ollama时是正常的\n";
    echo "   如需使用AI功能，请运行: ollama serve &\n";
    echo "   然后: ollama pull qwen3:4b\n";
}

echo "\n";

// 创建决策器
$decision_maker = new OllamaDXCCDecisionMaker($whitelist_manager);

// 测试用的DXCC实体
$test_dxcc_entities = array(
    array(
        'id' => '24',      // Bouvet Island - 稀有DXCC
        'name' => 'BOUVET',
        'flag' => 'BV'
    ),
    array(
        'id' => '199',     // Peter I Island - 稀有DXCC
        'name' => 'PETER I IS',
        'flag' => 'BV'
    ),
    array(
        'id' => '438',     // Madagascar - 常见但未通联的DXCC
        'name' => 'MADAGASCAR',
        'flag' => 'MG'
    ),
    array(
        'id' => '283',     // United States - 已通联的常见DXCC
        'name' => 'UNITED STATES',
        'flag' => 'US'
    )
);

echo "🧪 开始测试AI决策系统\n\n";

foreach ($test_dxcc_entities as $dxcc) {
    echo "测试DXCC实体: {$dxcc['name']} (ID: {$dxcc['id']})\n";
    
    // 测试是否应该CQ
    $should_cq = $decision_maker->shouldCQForDXCC($dxcc, '20m', 'FT8', 14074000, -10, date('His'));
    echo "  -> 是否建议CQ: " . ($should_cq ? "✅ 是" : "❌ 否") . "\n";
    
    // 测试波段特定策略
    $strategy = $decision_maker->getBandSpecificCQStrategy($dxcc, '20m', 'FT8', date('His'));
    echo "  -> 波段策略: {$strategy['cq_strategy']} (置信度: {$strategy['confidence']})\n";
    
    echo "\n";
}

echo "📊 白名单统计:\n";
$stats = $whitelist_manager->getWhitelistStats();
echo "  -> 全球白名单: {$stats['global']} 个实体\n";
echo "  -> 已通联缓存: {$stats['worked']} 个实体\n";

echo "\n✅ 测试完成！\n";
echo "💡 现在可以使用 robot_dxcc_enhanced_ollama.php 文件来运行AI增强的DXCC系统\n";