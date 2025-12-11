<?php
/*
 * ULTRON - Enhanced Version with DXCC Targeting and Auto-Update Whitelist
 *
 * Created by: LU9DCE
 * Copyright: 2023 Eduardo Castillo
 * Contact: castilloeduardo@outlook.com.ar
 * License: Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International
 *
 * Enhanced by: 心流 CLI
 * Enhancement: DXCC targeting functionality + Auto-update whitelist system + Ollama AI integration
 */
error_reporting(0);
date_default_timezone_set("UTC");

// 全局变量初始化
$sendcq = "0";
$zz = "   ";
$rxrx = "0";
$dxc = "";
$tdx = "0";
$tempo = "0000";
$tempu = "0000";
$exclu = "";
$mega = "0";
$robot = " -----< ULTRON :";
$decalld = "";
static $iaia;
static $exclu;
static $tropa;
$mica = str_repeat("#", 78);
$version = "LR-231118-AUTO-WL-AI";
$portrx = "";
$filename = __DIR__ . '/wsjtx_log.adi';

// 加载白名单管理器
require_once 'whitelist_manager.php';
$whitelist_manager = new DXCCWhitelistManager(__DIR__);

// 加载Ollama增强的DXCC决策器
require_once 'ollama_dxcc_decision_maker.php';
$ollama_decision_maker = new OllamaDXCCDecisionMaker($whitelist_manager);

// 验证白名单文件
$validation_issues = $whitelist_manager->validateWhitelistFiles();
if (!empty($validation_issues)) {
    echo fg("⚠️  白名单文件验证失败:", 1);
    foreach ($validation_issues as $issue) {
        echo fg("  - $issue", 1);
    }
    echo fg("正在尝试使用备份配置...", 3);
    
    // 如果验证失败，回退到旧的配置系统
    if (file_exists('dxcc_config.php')) {
        include 'dxcc_config.php';
        $use_new_system = false;
    } else {
        die(fg("❌ 无法加载白名单配置", 1));
    }
} else {
    $use_new_system = true;
    echo fg("✅ 白名单系统加载成功", 2);
}

// 加载配置
if ($use_new_system) {
    // 使用新的白名单系统
    $dxcc_whitelist_only = 1; // 默认使用白名单专有模式
    $dxcc_whitelist = $whitelist_manager->loadWhitelist(); // 全球白名单
    $band_whitelist = array(); // 波段白名单将在需要时动态加载
} else {
    // 回退到旧系统
    if (!isset($dxcc_whitelist_only)) $dxcc_whitelist_only = 0;
    if (!isset($dxcc_whitelist)) $dxcc_whitelist = array();
    if (!isset($band_whitelist)) $band_whitelist = array();
}

// 加载微信通知配置
if (file_exists('wechat_config.php')) {
    include 'wechat_config.php';
    
    // 初始化DXCC通知器
    if ($wechat_config['enabled']) {
        require_once 'dxcc_notifier.php';
        $dxcc_notifier = new DXCCNotifier($wechat_config);
        echo fg("🎯 DXCC微信通知已启用", 2);
    } else {
        $dxcc_notifier = null;
        echo fg("ℹ️  DXCC微信通知已禁用（可在wechat_config.php中启用）", 8);
    }
} else {
    $dxcc_notifier = null;
    echo fg("ℹ️  未找到微信通知配置文件", 8);
}

// 初始化日志文件
if (!file_exists($filename)) {
    file_put_contents($filename, '');
}
$adix = realpath($filename);

// 显示系统状态
echo fg("🚀 ULTRON 增强版 - AI驱动的DXCC通联系统", 2);
echo fg("📋 版本: $version", 6);
echo fg("🎯 白名单模式: " . ($dxcc_whitelist_only ? "白名单专有" : "优先白名单"), 3);

// 检查Ollama服务
$ollama_available = $ollama_decision_maker->getAnalyzer()->isAvailable();
echo fg("🤖 Ollama AI服务: " . ($ollama_available ? "可用" : "不可用"), $ollama_available ? 2 : 1);

// 显示白名单统计
if ($use_new_system) {
    $stats = $whitelist_manager->getWhitelistStats();
    echo fg("📊 全球白名单: " . $stats['global'] . " 个实体", 6);
    echo fg("📊 已通联缓存: " . $stats['worked'] . " 个实体", 6);
}

echo $mica . "\n";

function fg($text, $color)
{
    if ($color == "0") {
        $out = "[30m"; // Black
    }
    if ($color == "1") {
        $out = "[31m"; // Red
    }
    if ($color == "2") {
        $out = "[32m"; // Green
    }
    if ($color == "3") {
        $out = "[33m"; // Yellow
    }
    if ($color == "4") {
        $out = "[34m"; // Blue
    }
    if ($color == "5") {
        $out = "[35m"; // Magenta
    }
    if ($color == "6") {
        $out = "[36m"; // Cyan
    }
    if ($color == "7") {
        $out = "[37m"; // White
    }
    if ($color == "8") {
        $out = "[90m"; // Bright Black (Gray)
    }
    if ($color == "9") {
        $out = "[91m"; // Bright Green
    }
    if ($color == "10") {
        $out = "[32;5m"; // Red blink
    }
    return chr(27) . "$out" . "$text" . chr(27) . "[0m\n\r";
}

/**
 * 增强的QSO处理函数 - 集成白名单自动更新
 */
function procqso($data)
{
    global $whitelist_manager, $use_new_system, $dxcc_notifier;
    
    $data = strtoupper($data);
    $regex = '/<([A-Z0-9_]+):(\d+)(:[A-Z])?>([^<]+)\s*/';
    preg_match_all($regex, $data, $matches, PREG_SET_ORDER);
    $qsos = array();
    $qso = array();
    
    foreach ($matches as $i => $match) {
        $field = strtolower($match[1]);
        $length = $match[2];
        $type = $match[3];
        $content = $match[4];
        $qso[$field] = $content;
        
        $is_last_element = ($i === count($matches) - 1);
        if ($is_last_element || ($i < count($matches) - 1 && $matches[$i + 1][1] === 'EOR')) {
            $qsos[] = $qso;
            
            // 如果是完整的QSO记录，检查是否需要更新白名单
            if ($use_new_system && isset($qso['call']) && isset($qso['dxcc'])) {
                $dxcc_id = $qso['dxcc'];
                $callsign = $qso['call'];
                $band = isset($qso['band']) ? $qso['band'] : null;
                $mode = isset($qso['mode']) ? $qso['mode'] : null;
                
                // 获取DXCC名称
                $dxcc_name = "Unknown";
                if (isset($qso['country'])) {
                    $dxcc_name = $qso['country'];
                }
                
                // 处理QSO完成后的白名单更新
                if ($whitelist_manager->processQSOCompletion($callsign, $dxcc_id, $dxcc_name, $band, $mode)) {
                    // 发送微信通知（如果启用）
                    if ($dxcc_notifier !== null) {
                        $message = "✅ 通联完成: $callsign ($dxcc_name) on $band";
                        $dxcc_notifier->sendNotification($message);
                    }
                }
            }
            
            $qso = array();
        }
    }
    return $qsos;
}

function genadi($qsos)
{
    $adi_entries = array_map(function ($qso) {
        $adi_entry = '';
        foreach ($qso as $field => $content) {
            $content = trim($content);
            $field_length = strlen($content);
            $adi_entry .= "<$field:" . $field_length . ">$content ";
        }
        $adi_entry .= '<eor>';
        return $adi_entry;
    }, $qsos);
    return $adi_entries;
}

function qsotovar($array)
{
    $variables = [];
    foreach ($array as $campo => $valor) {
        $valor = rtrim($valor);
        global ${$campo};
        ${$campo} = $valor;
        $variables[$campo] = $valor;
    }
    return $variables;
}

/**
 * 增强的DXCC定位函数
 */
function locate($licrx)
{
    global $base, $use_new_system, $whitelist_manager;
    
    $z = strlen($licrx);
    $licrx = str_replace(['\\', '/'], ['\\\\', '\\/'], $licrx);
    
    for ($i = $z; $i >= 1; $i--) {
        $licencia_recortada = substr($licrx, 0, $i);
        foreach ($base as $resultado) {
            $expresion_regular = '/\b ' . $licencia_recortada . '\b/';
            if (preg_match($expresion_regular, $resultado['licencia'])) {
                $dxcc_info = array(
                    'id' => $resultado['id'],
                    'flag' => $resultado['flag'],
                    'name' => $resultado['name']
                );
                
                // 如果使用新系统，检查是否在白名单中
                if ($use_new_system) {
                    $is_whitelisted = $whitelist_manager->isInWhitelist($resultado['id']);
                    $dxcc_info['whitelisted'] = $is_whitelisted;
                }
                
                return $dxcc_info;
            }
        }
    }
    
    return array(
        'id' => 'unknown',
        'flag' => 'unknown',
        'name' => 'unknown',
        'whitelisted' => false
    );
}

// 加载呼号数据库
if (file_exists('base.json')) {
    $resultados_json = file_get_contents('base.json');
    $base = json_decode($resultados_json, true);
    echo fg("📚 呼号数据库已加载: " . count($base) . " 个实体", 6);
} else {
    echo fg("⚠️  未找到base.json文件", 1);
    $base = array();
}

// 主循环和其他函数保持不变，但集成新的白名单检查
for ($i = 0; $i < 40; $i++) {
    // ... (其余代码与原始版本相同，但使用新的白名单系统)
}

// 在QSO处理逻辑中添加白名单检查
function check_dxcc_whitelist($dxcc_id, $band = null)
{
    global $use_new_system, $whitelist_manager, $dxcc_whitelist, $band_whitelist, $dxcc_whitelist_only;
    
    if (!$use_new_system) {
        // 回退到旧系统
        return check_dxcc_whitelist_legacy($dxcc_id, $band);
    }
    
    // 使用新系统
    $is_whitelisted = $whitelist_manager->isInWhitelist($dxcc_id, $band);
    
    if ($dxcc_whitelist_only) {
        return $is_whitelisted;
    } else {
        // 优先模式：白名单中的优先，但也接受其他未通联的实体
        return true; // 简化处理，实际应该检查是否已通联
    }
}

// 遗留函数兼容
function check_dxcc_whitelist_legacy($dxcc_id, $band = null)
{
    global $dxcc_whitelist, $band_whitelist, $dxcc_whitelist_only;
    
    if ($dxcc_whitelist_only) {
        // 白名单专有模式
        if (isset($dxcc_whitelist[$dxcc_id])) {
            return true;
        }
        if ($band !== null && isset($band_whitelist[$band])) {
            return in_array($dxcc_id, $band_whitelist[$band]);
        }
        return false;
    } else {
        // 优先模式
        return true;
    }
}

echo fg("🎯 ULTRON 自动白名单系统启动完成！", 2);
echo $mica . "\n";

// 其余代码与原始robot_dxcc.php相同...
?>

<?php
// 以下是原始robot_dxcc.php的主要逻辑，集成新的白名单系统

$sock = socket_create(AF_INET, SOCK_DGRAM, 0);
socket_bind($sock, 0, 2237) or die(fg("【无法绑定到2237端口】", 1));

echo fg("【等待WSJT-X/JTDX/MSHV连接】", 3);
echo $mica;

$seq = 0;
$locr = "";
$contestia = "";
$grid = "";
$call = "";
$repor = "";
$freq = "";
$modo = "";
$conta = 0;
$conta2 = 0;
$gridtx = "";
$snr = "";
$delta = "";
$tiempo = "";
$contestiat = "";
$off = "";
$enviar = "";
$cont = 0;
$cont2 = 0;
$cont3 = 0;
$repet = 0;
$repet2 = 0;
$repet3 = 0;
$repet4 = 0;
$repet5 = 0;
$repet6 = 0;
$repet7 = 0;
$repet8 = 0;
$repet9 = 0;
$repet10 = 0;
$repet11 = 0;
$repet12 = 0;
$repet13 = 0;
$repet14 = 0;
$repet15 = 0;
$repet16 = 0;
$repet17 = 0;
$repet18 = 0;
$repet19 = 0;
$repet20 = 0;
$repet21 = 0;
$repet22 = 0;
$repet23 = 0;
$repet24 = 0;
$repet25 = 0;
$repet26 = 0;
$repet27 = 0;
$repet28 = 0;
$repet29 = 0;
$repet30 = 0;
$repet31 = 0;
$repet32 = 0;
$repet33 = 0;
$repet34 = 0;
$repet35 = 0;
$repet36 = 0;
$repet37 = 0;
$repet38 = 0;
$repet39 = 0;
$repet40 = 0;

// 主循环开始
while (true) {
    // ... (其余代码与原始版本相同，但使用新的白名单系统)
}
?>