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
$version = "LR-231118-AUTO-WL-AI-BATCH";
$portrx = "";
$filename = __DIR__ . '/wsjtx_log.adi';

// 批量处理相关变量
$decoded_signals_buffer = array();  // 解码信号缓冲区
$last_batch_process_time = time();  // 上次批量处理时间
$batch_process_interval = 15;       // 批量处理时间间隔（秒）
$last_time_processed = array();     // 记录已处理的时间戳

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

function procqso($data)
{
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

for ($i = 0; $i < 40; $i++) {
    echo "\n\r";
}
echo fg($mica, 1);
echo " Created by Eduardo Castillo - LU9DCE\n\r";
echo " (C) 2023 - castilloeduardo@outlook.com.ar\n\r";
echo fg($mica, 1);
echo "$robot Preparing :";
echo " Version $version\n\r";
echo " Looking for radio software wait ...";
goto test;

contr:
$resultados_json = file_get_contents('base.json');
$base = json_decode($resultados_json, true);
echo fg($mica, 5);
echo "$robot Ctrl + C to exit\n\r";
echo fg($mica, 1);
echo " -----> Info\n\r";
echo " -----> CQ active (0=NO/1=YES) - N\n\r";
echo " -----> Response time          - NNNN\n\r";
echo " -----> Time that ends         - NNNN\n\r";
echo " -----> Current time           - NNNN\n\r";
echo " -----> Contacts made to day   - NN\n\r";
echo fg($mica, 1);
echo " ADI    : $adix\n\r";
echo " Processing, please wait  : ";
echo " Whitelist Only Mode: " . ($dxcc_whitelist_only ? "ON" : "OFF") . "\n\r";

// 显示文件大小和预估处理时间
$file_size = filesize($adix);
if ($file_size > 0) {
    $estimated_qsos = intval($file_size / 150); // 平均每条记录约150字节
    echo " File size: " . number_format($file_size) . " bytes (~$estimated_qsos QSOs)\n\r";
    echo " Optimized processing enabled...\n\r";
}

// 分析日志文件，识别已通联的DXCC实体
$cotcot = 0;
$contents = "";
$worked_dxcc = array(); // 已通联的DXCC实体
$worked_dxcc_bands = array(); // 按波段记录已通联的DXCC实体

// 优化ADI文件处理 - 支持单行和多行ADIF格式
$archivoEntrada = fopen($adix, 'r');
if (!$archivoEntrada) {
    die("无法打开ADI文件: $adix\n");
}

// 预编译正则表达式提高性能 - 修正为支持单行ADIF格式
$eor_pattern = '/<eor>/i';
$field_pattern = '/<([A-Z0-9_]+):(\d+)(:[A-Z])?>([^<]*)/i';

// 频率到波段映射表（缓存优化）
$freq_to_band_map = array(
    array(1800000, 2000000, '160m'),
    array(3500000, 4000000, '80m'),
    array(7000000, 7300000, '40m'),
    array(10100000, 10150000, '30m'),
    array(14000000, 14350000, '20m'),
    array(18068000, 18168000, '17m'),
    array(21000000, 21450000, '15m'),
    array(24890000, 24990000, '12m'),
    array(28000000, 29700000, '10m'),
    array(50000000, 54000000, '6m')
);

// 逐行处理ADIF文件
$current_qso = '';
$processed_count = 0;

while (!feof($archivoEntrada)) {
    $line = fgets($archivoEntrada);
    if ($line === false) break;
    
    $line = trim($line);
    if (empty($line)) continue;
    
    $current_qso .= $line . ' ';
    
    // 检查是否遇到eor标记（支持单行和多行格式）
    if (preg_match($eor_pattern, $line)) {
        // 解析QSO记录
        if (preg_match_all($field_pattern, $current_qso, $matches, PREG_SET_ORDER)) {
            $qso = array();
            foreach ($matches as $match) {
                $field = strtolower($match[1]);
                $qso[$field] = trim($match[4]);
            }
            
            if (!empty($qso['call'])) {
                $call = $qso['call'];
                $contents .= $call . ' ';
                $cotcot++;
                $processed_count++;
                
                // 快速DXCC识别（缓存优化）
                $dxcc_info = locate($call);
                if ($dxcc_info && isset($dxcc_info['id'])) {
                    $dxcc_id = $dxcc_info['id'];
                    $worked_dxcc[$dxcc_id] = $dxcc_info['name'];
                    
                    // 快速波段推断
                    $current_band = '';
                    if (isset($qso['band'])) {
                        $current_band = $qso['band'];
                    } elseif (isset($qso['freq'])) {
                        $freq = intval($qso['freq']);
                        foreach ($freq_to_band_map as $range) {
                            if ($freq >= $range[0] && $freq < $range[1]) {
                                $current_band = $range[2];
                                break;
                            }
                        }
                    }
                    
                    if ($current_band) {
                        if (!isset($worked_dxcc_bands[$current_band])) {
                            $worked_dxcc_bands[$current_band] = array();
                        }
                        $worked_dxcc_bands[$current_band][$dxcc_id] = $dxcc_info['name'];
                    }
                }
            }
        }
        
        // 重置当前QSO缓存
        $current_qso = '';
        
        // 进度显示（每处理500条记录）
        if ($processed_count % 500 == 0) {
            echo "  已处理 $processed_count 条记录...\r";
            flush();
        }
    }
}

fclose($archivoEntrada);
echo "  共处理 $cotcot 条记录完成！    \n";

echo "[OK]\n\r";
echo " $cotcot Processed contacts\n\r";
echo " PortRx : $portrx\n\r";

// 显示白名单设置
if (!empty($dxcc_whitelist)) {
    echo " DXCC Whitelist: ";
    foreach ($dxcc_whitelist as $dxcc_id => $dxcc_name) {
        echo $dxcc_name . " ";
    }
    echo "\n\r";
}

if (!empty($band_whitelist)) {
    echo " Band Whitelist: ";
    foreach ($band_whitelist as $band => $dxcc_list) {
        echo "$band(";
        foreach ($dxcc_list as $dxcc_id) {
            $dxcc_info = get_dxcc_info_by_id($dxcc_id);
            if ($dxcc_info) {
                echo $dxcc_info['name'] . " ";
            }
        }
        echo ") ";
    }
    echo "\n\r";
}

echo fg($mica, 4);

function sendcq()
{
    global $ipft, $portrx, $magic, $ver, $largoid, $id, $time, $snr, $deltat, $deltaf, $lmode, $mode, $ml, $message, $low, $off;
    $fp = stream_socket_client("udp://$ipft:$portrx", $errno, $errstr);
    $msg = "$magic$ver" . "00000004" . "$largoid$id$time$snr$deltat$deltaf$lmode$mode$ml$message$low$off";
    $msg = hex2bin($msg);
    fwrite($fp, $msg);
    fclose($fp);
    $sendcq = "1";
    
    return $sendcq;
}

// DXCC查找缓存（性能优化）
static $dxcc_cache = array();

function locate($licrx)
{
    global $base, $dxcc_cache;
    
    // 检查缓存
    if (isset($dxcc_cache[$licrx])) {
        return $dxcc_cache[$licrx];
    }
    
    $z = strlen($licrx);
    $licrx = str_replace(['\\', '/'], ['\\\\', '\\/'], $licrx);
    
    // 预编译正则表达式优化
    $clean_pattern = '/[\/\(].*$/';
    
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
                    $clean_prefix = preg_replace($clean_pattern, '', $prefix);
                    if ($clean_prefix === $licencia_recortada) {
                        $result = array(
                            'id' => $resultado['id'],
                            'flag' => $resultado['flag'],
                            'name' => $resultado['name']
                        );
                        // 缓存结果
                        $dxcc_cache[$licrx] = $result;
                        return $result;
                    }
                }
            }
        }
    }
    
    $result = array(
        'id' => 'unknown',
        'flag' => 'unknown',
        'name' => 'unknown'
    );
    
    // 缓存未找到的结果
    $dxcc_cache[$licrx] = $result;
    return $result;
}

function get_dxcc_info_by_id($dxcc_id) {
    global $base;
    foreach ($base as $resultado) {
        if ($resultado['id'] == $dxcc_id) {
            return array(
                'id' => $resultado['id'],
                'flag' => $resultado['flag'],
                'name' => $resultado['name']
            );
        }
    }
    return null;
}

function vicen($licencia)
{
    $patron = '/^[A-Z]{1,2}\d{1}[A-Z]{1,3}$/i';
    if (preg_match($patron, $licencia)) {
        return true;
    } else {
        return false;
    }
}

echo "$robot Watchdog = 90s\n\r";
echo "$robot Pls disable watchdog of $soft\n\r";
echo fg($mica, 4);
echo "$robot $ipft port udp 2237\n\r";
echo "$robot forward to 127.0.0.1 port udp 2277\n\r";
echo fg($mica, 1);
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_bind($socket, '0.0.0.0', 2237);
$read = [
    $socket,
];
$socketx = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
$write = null;
$except = null;
trama:
socket_select($read, $write, $except, null);
$datas = socket_recvfrom($socket, $buffer, 512, 0, $fromip, $portrx);
$data = $buffer;
socket_sendto($socketx, $data, 512, 0, '127.0.0.1', 2277);
$lee = bin2hex($data);
$type = substr($lee, 16, 8);
if ($sendcq == "1" && $led) {
    shell_exec($ledron);
}
if ($sendcq == "0" && $led) {
    shell_exec($ledroff);
}
if ($type == "00000000") {
    goto tcero;
}
if ($type == "00000001") {
    goto tuno;
}
if ($type == "00000002") {
    goto tdos;
}
if ($type == "00000005") {
    //goto tcin;
}
if ($type == "0000000c") {
    goto tdoce;
}
goto trama;

tcero:
$info = strtotime("now");
if (date("H:i") === "00:00") {
    $mega = "0";
}
$qq = "$robot $soft = $sendcq-" . substr($tempo, -4) . "-" . substr($tempu, -4) . "-" . substr($info, -4) . "-" . $mega;
echo fg($qq, 7);
if ($sendcq == "1" && $info > $tempu) {
    goto dog;
}
$txw = date("i");
if (($txw == "00") || ($txw == "30")) {
    unset($exclu);
}
goto trama;

tuno:
$magic = substr($lee, 0, 8);
$magicd = hexdec($magic);
$ver = substr($lee, 8, 8);
$verd = hexdec($ver);
$type = substr($lee, 16, 8);
$typed = hexdec($type);
$largoid = substr($lee, 24, 8);
$largoidd = hexdec($largoid);
$larg = hexdec($largoid) * 2;
$id = substr($lee, 32, $larg);
$idd = hex2bin($id);
$soft = $idd;
$con = 32 + $larg;
$freq = substr($lee, $con, 16);
$freqd = hexdec($freq);
$con = $con + 16;
$lmode = substr($lee, $con, 8);
$lmoded = hexdec($lmode) * 2;
$con = $con + 8;
$mode = substr($lee, $con, $lmoded);
$moded = hex2bin($mode);
$con = $con + $lmoded;
$ldxcall = substr($lee, $con, 8);
if ($ldxcall == "ffffffff") {
    $ldxcall = "0";
}
$ldxcalld = hexdec($ldxcall) * 2;
$con = $con + 8;
$dxcall = substr($lee, $con, $ldxcalld);
$dxcalld = hex2bin($dxcall);
$con = $con + $ldxcalld;
$lreport = substr($lee, $con, 8);
$lreportd = hexdec($lreport) * 2;
$con = $con + 8;
$report = substr($lee, $con, $lreportd);
$reportd = hex2bin($report);
$con = $con + $lreportd;
$ltxmode = substr($lee, $con, 8);
$ltxmoded = hexdec($ltxmode) * 2;
$con = $con + 8;
$txmode = substr($lee, $con, $ltxmoded);
$txmoded = hex2bin($txmode);
$con = $con + $ltxmoded;
$txenable = substr($lee, $con, 2);
$txenabled = hexdec($txenable);
$con = $con + 2;
$transmitting = substr($lee, $con, 2);
$transmittingd = hexdec($transmitting);
$con = $con + 2;
$decoding = substr($lee, $con, 2);
$decodingd = hexdec($decoding);
$con = $con + 2;
$rxdf = substr($lee, $con, 8);
$rxdfd = hexdec($rxdf);
$con = $con + 8;
$txdf = substr($lee, $con, 8);
$txdfd = hexdec($txdf);
$con = $con + 8;
$ldecall = substr($lee, $con, 8);
$ldecalld = hexdec($ldecall) * 2;
$con = $con + 8;
$decall = substr($lee, $con, $ldecalld);
$decalld = hex2bin($decall);
$con = $con + $ldecalld;
$ldegrid = substr($lee, $con, 8);
$ldegridd = hexdec($ldecall) * 2;
$con = $con + 8;
$degrid = substr($lee, $con, $ldegridd);
$degridd = hex2bin($degrid);
$con = $con + $ldegridd;
$ldxgrid = substr($lee, $con, 8);
if ($ldxgrid == "ffffffff") {
    $ldxgrid = "0";
}
$ldxgridd = hexdec($ldxgrid) * 2;
$con = $con + 8;
$dxgrid = substr($lee, $con, $ldxgridd);
$dxgridd = hex2bin($dxgrid);
$con = $con + $ldxgridd;
$watchdog = substr($lee, $con, 2);
$watchdogd = hexdec($watchdog);
if ($decodingd == "0" && $rxrx > "0") {
    $qq = "$robot " . date("Y-m-d H:i:s") . " --------------- " . sprintf("%04d", $rxrx) . " Decodeds -----------";
    echo fg($qq, 6);
    $rxrx = 0;
}
if ($txenabled == "1") {
    $tdx = $tdx + 1;
}
if ($tdx == "2") {
    echo fg("$robot Trasmiting @ $dxc", 9);
}
if ($txenabled == "1" && $sendcq == "0") {
    goto toch;
}
goto trama;

tdos:
$lee = bin2hex($data);
$type = substr($lee, 16, 8);
$magic = substr($lee, 0, 8);
$magicd = hexdec($magic);
$ver = substr($lee, 8, 8);
$verd = hexdec($ver);
$type = substr($lee, 16, 8);
$typed = hexdec($type);
$largoid = substr($lee, 24, 8);
$largoidd = hexdec($largoid);
$larg = hexdec($largoid) * 2;
$id = substr($lee, 32, $larg);
$idd = hex2bin($id);
$soft = $idd;
$con = 32 + $larg;
$newdecode = substr($lee, $con, 2);
$newdecoded = hexdec($newdecode);
$con = $con + 2;
$time = substr($lee, $con, 8);
$mil = hexdec($time);
$seconds = ceil($mil / 1000);
$timed = date("His", $seconds);
$con = $con + 8;
$snr = substr($lee, $con, 8);
$snrd = unpack("l", pack("l", hexdec($snr)))[1];
$con = $con + 8;
$deltat = substr($lee, $con, 16);
$con = $con + 16;
$deltaf = substr($lee, $con, 8);
$deltafd = unpack("l", pack("l", hexdec($deltaf)))[1];
$con = $con + 8;
$lmode = substr($lee, $con, 8);
$lmoded = hexdec($lmode) * 2;
$con = $con + 8;
$mode = substr($lee, $con, $lmoded);
$moded = hex2bin($mode);
$con = $con + $lmoded;
$ml = substr($lee, $con, 8);
$mld = hexdec($ml) * 2;
$con = $con + 8;
$message = substr($lee, $con, $mld);
$messaged = hex2bin($message);
$con = $con + $mld;
$low = substr($lee, $con, 2);
$lowd = hex2bin($low);
$con = $con + 2;
$off = substr($lee, $con, 2);
$offd = hex2bin($off);
goto store_signal;

utex:
$rxrx = $rxrx + 1;
$tdx = "0";
goto trama;

tcua:
if ($zz == ">>") {
    sendcq();
}
$sendcq = "1";
$zz = "   ";

echo fg("$robot I see @ $dxc in $qio", 9);
$tempo = strtotime("now");
$tempu = $tempo + 90;
goto trama;

toch:
$fp = stream_socket_client("udp://$ipft:$portrx", $errno, $errstr);
$msg = "$magic$ver" . "00000008" . "$largoid$id" . "00";
$msg = hex2bin($msg);
fwrite($fp, $msg);
fclose($fp);
$sendcq = "0";
$zz = "   ";
$dxc = "";
$tdx = "0";
$tempo = "0000";
$tempu = "0000";
$dxc = "";

echo fg("$robot Halt Tx", 5);
goto trama;

dog:
echo fg("$robot $dxc Not respond to the call", 5);
$exclu[$dxc] = $dxc;
$dxc = "";
goto toch;

// 跳过单个信号的处理，因为将在批量处理中处理
$rxrx = $rxrx + 1;
$tdx = "0";
goto trama;

// 获取当前波段信息
$band_info = "";
if (isset($freqd)) {
    if ($freqd >= 1800000 && $freqd < 2000000) $band_info = "160m";
    else if ($freqd >= 3500000 && $freqd < 4000000) $band_info = "80m";
    else if ($freqd >= 7000000 && $freqd < 7300000) $band_info = "40m";
    else if ($freqd >= 10100000 && $freqd < 10150000) $band_info = "30m";
    else if ($freqd >= 14000000 && $freqd < 14350000) $band_info = "20m";
    else if ($freqd >= 18068000 && $freqd < 18168000) $band_info = "17m";
    else if ($freqd >= 21000000 && $freqd < 21450000) $band_info = "15m";
    else if ($freqd >= 24890000 && $freqd < 24990000) $band_info = "12m";
    else if ($freqd >= 28000000 && $freqd < 29700000) $band_info = "10m";
}

if (isset($iaia[$lin[1]]) && sizeof($lin) == 3 && $lin[1] != $decalld && ($lin[0] == "CQ" || $lin[2] == "73" || $lin[2] == "RR73" || $lin[2] == "RRR")) {
    $zz = "--";
    $fg = "1";
    goto shsh;
}

$searchfor = $lin[1];

// 检查是否在白名单中
$dxcc_info = locate($lin[1]);
$dxcc_id = $dxcc_info ? $dxcc_info['id'] : null;

// 检查DXCC白名单
$in_dxcc_whitelist = false;
if (!empty($dxcc_whitelist) && $dxcc_id) {
    $in_dxcc_whitelist = in_array($dxcc_id, array_keys($dxcc_whitelist));
}

// 检查波段白名单
$in_band_whitelist = false;
if (!empty($band_whitelist) && $band_info && $dxcc_id) {
    if (isset($band_whitelist[$band_info])) {
        $in_band_whitelist = in_array($dxcc_id, $band_whitelist[$band_info]);
    }
}

// 检查各种状态 - 按照正确的优先级
$never_worked_global = false;
$worked_in_band = false;

if ($dxcc_id) {
    $never_worked_global = !isset($worked_dxcc[$dxcc_id]);  // 从未通联过（全局）
    if ($band_info && isset($worked_dxcc_bands[$band_info][$dxcc_id])) {
        $worked_in_band = true;  // 该波段已通联
    }
}

// 正确的DXCC优先级逻辑
$should_respond = false;
$priority_reason = "";

// 使用Ollama AI进行决策
global $ollama_decision_maker;
$ollama_available = false;
$ollama_decision_result = false;

if (isset($ollama_decision_maker) && $dxcc_info && $dxcc_info['id'] != 'unknown') {
    try {
        $ollama_available = $ollama_decision_maker->getAnalyzer()->isAvailable();
        if ($ollama_available) {
            // 收集当前解码信号的上下文
            $all_decoded_signals = array();
            $signal_context = array(
                'call' => $lin[1],
                'dxcc' => $dxcc_info['id'],
                'message' => $mess,
                'snr' => $snrd,
                'time' => $timed,
                'band' => $band_info
            );
            $all_decoded_signals[] = $signal_context;
            
            $ollama_decision_result = $ollama_decision_maker->shouldCQForDXCC(
                $dxcc_info,
                $band_info,
                $moded,
                $freqd,
                $snrd,
                $timed,
                $all_decoded_signals
            );
            
            if ($ollama_decision_result) {
                $should_respond = true;
                $priority_reason = "AI-RECOMMENDED";
            } else {
                $should_respond = false;
            }
        }
    } catch (Exception $e) {
        echo fg("🤖 Ollama决策错误: " . $e->getMessage(), 1);
        $ollama_available = false;
    }
}

// 如果Ollama不可用或未启用，则使用传统逻辑
if (!$ollama_available) {
    if ($never_worked_global) {
        // 🏆 超级优先级：从未通联过的DXCC（比任何白名单都重要）
        $should_respond = true;
        $priority_reason = "NEW DXCC";
    } elseif ($in_dxcc_whitelist && !$worked_in_band) {
        // 🥇 优先级1：全局白名单且该波段未通联
        $should_respond = true;
        $priority_reason = "GLOBAL WL";
    } elseif ($in_band_whitelist && !$worked_in_band) {
        // 🥈 优先级2：波段白名单且该波段未通联
        $should_respond = true;
        $priority_reason = "BAND WL";
    } elseif ($dxcc_whitelist_only == 0 && !$never_worked_global && !$worked_in_band) {
        // 🥉 优先级3：优先模式下，其他未通联的DXCC
        $should_respond = true;
        $priority_reason = "NEW BAND";
    }
}

// 根据优先级决定是否响应
if ($should_respond && sizeof($lin) == 3 && $lin[1] != $decalld && $sendcq == "0" && ($lin[0] == "CQ" || $lin[2] == "73" || $lin[2] == "RR73" || $lin[2] == "RRR")) {
    $zz = ">>";
    $fg = "2";
} else {
    // 不满足条件，标记为不响应
    $zz = "##";
    $fg = "8";
}

if (intval(trim($snrd)) <= -20 && $zz == ">> ") {
    $zz = "Lo";
    $fg = "3";
}
if (isset($exclu[$lin[1]])) {
    $zz = "XX";
    $fg = "4";
}
if (!vicen($lin[1])) {
    $zz = "FL";
    $fg = "8";
}
if (@strpos($messaged, $dxc) !== false && $sendcq == "1") {
    $fg = "2";
}

shsh:
if (isset($tropa[$lin[1]])) {
    $qio = $tropa[$lin[1]];
} else {
    $qio = locate($lin[1]);
    $qio = $qio['name'];
    $tropa[$lin[1]] = $qio;
}

if (isset($led)) {
    if ($led) {
        shell_exec($ledvon);
    }
}

$modedx = trim($moded);
if ($modedx == "`") {
    $modedx = "FST4";
}
if ($modedx == "+") {
    $modedx = "FT4";
}
if ($modedx == "~") {
    $modedx = "FT8";
}
if ($modedx == "$") {
    $modedx = "JT4";
}
if ($modedx == "@") {
    $modedx = "JT9";
}
if ($modedx == "#") {
    $modedx = "JT65";
}
if ($modedx == ":") {
    $modedx = "Q65";
}
if ($modedx == "&") {
    $modedx = "MSK144";
}

$timed = str_pad(substr($timed, 0, 6), 6);
$snrd = str_pad(substr($snrd, 0, 3), 3);
$deltafd = str_pad(substr($deltafd, 0, 4), 4);
$moded = str_pad(substr($moded, 0, 4), 4);
$messaged = str_pad(substr($messaged, 0, 20), 20);
$zz = str_pad(substr($zz, 0, 2), 2);
$qio = str_pad(substr($qio, 0, 20), 20);
$modedx = str_pad(substr($modedx, 0, 6), 6);

// 添加波段信息显示
$band_display = $band_info ? "[$band_info]" : "";

$qq = "$timed  $snrd  $deltafd  $modedx  $zz $messaged - $qio $band_display";

// 添加优先级标记和原因
if ($should_respond && isset($priority_reason)) {
    $qq .= " [$priority_reason]";
} else if ($in_dxcc_whitelist || $in_band_whitelist) {
    $qq .= " [WL]";
}

if (isset($led)) {
    if ($led) {
        shell_exec($ledvoff);
    }
}

// 实时DXCC活动检测 - 发现未通联DXCC立即通知
global $dxcc_notifier;
if ($dxcc_notifier && isset($lin[1]) && $lin[1] != '' && $lin[1] != 'CQ') {
    $call = $lin[1];
    $band = $band_info ?? 'unknown';
    $mode = $modedx ?? 'unknown';
    $snr = trim($snrd) ?? '0';
    $time = date('Y-m-d H:i:s');
    
    // 检测DXCC实时活动
    $activity_result = $dxcc_notifier->check_dxcc_activity($call, $band, $mode, $snr, $time);
    if ($activity_result) {
        // 在输出中添加特殊标记
        $qq .= " [NEW DXCC ACTIVITY!]";
        // 使用特殊颜色突出显示
        if ($fg == "2") $fg = "10"; // 如果是绿色，改为闪烁绿色
    }
}

echo fg($qq, $fg);

// 只有在不是Web API控制的CQ时，才在目标台站回应时自动停止发射
if ($lin[0] != $decalld && $lin[0] != "CQ" && $lin[1] == $dxc && ($lin[2] != "73" || $lin[2] != "RR73")) {
    echo fg("$robot Busy?", 4);
    $dxc = "";
    goto toch;
}
if ($lin[0] == $decalld && $lin[2] == "73") {
    echo fg("$robot Qso confirmed successfully", 10);
    $mega = $mega + 1;
    
    // 检查是否是新的DXCC实体并发送微信通知
    if ($dxcc_notifier && isset($lin[1])) {
        $call = $lin[1];
        $band = $band_info ?? 'unknown';
        $mode = $modedx ?? 'unknown';
        $time = date('Y-m-d H:i:s');
        
        // 检测新DXCC并发送通知
        $notifier_result = $dxcc_notifier->check_new_dxcc($call, $band, $mode, $time);
        if ($notifier_result) {
            echo fg("🎯 检测到新的DXCC实体，微信通知已发送！", 2);
        }
    }
    
    // 更新白名单，移除已完成QSO的实体
    if (isset($lin[1])) {
        $dxcc_info = locate($lin[1]);
        if ($dxcc_info && $dxcc_info['id'] !== 'unknown') {
            // 尝试加载白名单管理器并更新白名单
            if (!isset($whitelist_manager) && file_exists('whitelist_manager.php')) {
                require_once 'whitelist_manager.php';
                $whitelist_manager = new DXCCWhitelistManager(__DIR__);
            }
            
            if (isset($whitelist_manager)) {
                $whitelist_manager->processQSOCompletion($lin[1], $dxcc_info['id'], $dxcc_info['name'], $band_info, $modedx);
                echo fg("✅ 已将 {$dxcc_info['name']} 从白名单中移除", 2);
            }
        }
    }
    
    goto toch;
}
if ($lin[0] == $decalld && $lin[2] != "73" && $sendcq == "0") {
    echo fg("$robot Reply? @ $lin[1]", 6);
    $zz = ">>";
}
if ($zz == ">>" && $sendcq == "0") {
    $dxc = $lin[1];
    goto tcua;
}
goto utex;

test:
$host = '0.0.0.0';
$port = 2237;
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$socket) {
    die("无法创建UDP套接字: " . socket_strerror(socket_last_error()) . "\n");
}
socket_bind($socket, $host, $port);
echo " [OK]\n\r";
echo " Listening on UDP port $port...\n\r";
while (true) {
    $from = "0.0.0.0";
    $port = 0;
    socket_recvfrom($socket, $buffer, 512, 0, $from, $port);
    $lee = bin2hex($buffer);
    $type = substr($lee, 16, 8);
    if ($type == "00000001") {
        $magic = substr($lee, 0, 8);
        $magicd = hexdec($magic);
        $ver = substr($lee, 8, 8);
        $verd = hexdec($ver);
        $type = substr($lee, 16, 8);
        $typed = hexdec($type);
        $largoid = substr($lee, 24, 8);
        $largoidd = hexdec($largoid);
        $larg = hexdec($largoid) * 2;
        $id = substr($lee, 32, $larg);
        $idd = hex2bin($id);
        $con = 32 + $larg;
        $freq = substr($lee, $con, 16);
        $freqd = hexdec($freq);
        $con = $con + 16;
        $lmode = substr($lee, $con, 8);
        $lmoded = hexdec($lmode) * 2;
        $con = $con + 8;
        $mode = substr($lee, $con, $lmoded);
        $moded = hex2bin($mode);
        $con = $con + $lmoded;
        $ldxcall = substr($lee, $con, 8);
        if ($ldxcall == "ffffffff") {
            $ldxcall = "0";
        }
        $ldxcalld = hexdec($ldxcall) * 2;
        $con = $con + 8;
        $dxcall = substr($lee, $con, $ldxcalld);
        $dxcalld = hex2bin($dxcall);
        $con = $con + $ldxcalld;
        $lreport = substr($lee, $con, 8);
        $lreportd = hexdec($lreport) * 2;
        $con = $con + 8;
        $report = substr($lee, $con, $lreportd);
        $reportd = hex2bin($report);
        $con = $con + $lreportd;
        $ltxmode = substr($lee, $con, 8);
        $ltxmoded = hexdec($ltxmode) * 2;
        $con = $con + 8;
        $txmode = substr($lee, $con, $ltxmoded);
        $txmoded = hex2bin($txmode);
        $con = $con + $ltxmoded;
        $txenable = substr($lee, $con, 2);
        $txenabled = hexdec($txenable);
        $con = $con + 2;
        $transmitting = substr($lee, $con, 2);
        $transmittingd = hexdec($transmitting);
        $con = $con + 2;
        $decoding = substr($lee, $con, 2);
        $decodingd = hexdec($decoding);
        $con = $con + 2;
        $rxdf = substr($lee, $con, 8);
        $rxdfd = hexdec($rxdf);
        $con = $con + 8;
        $txdf = substr($lee, $con, 8);
        $txdfd = hexdec($txdf);
        $con = $con + 8;
        $ldecall = substr($lee, $con, 8);
        $ldecalld = hexdec($ldecall) * 2;
        $con = $con + 8;
        $decall = substr($lee, $con, $ldecalld);
        $decalld = hex2bin($decall);
        $con = $con + $ldecalld;
        $ldegrid = substr($lee, $con, 8);
        $ldegridd = hexdec($ldecall) * 2;
        $con = $con + 8;
        $degrid = substr($lee, $con, $ldegridd);
        $degridd = hex2bin($degrid);
        $con = $con + $ldegridd;
        $ldxgrid = substr($lee, $con, 8);
        if ($ldxgrid == "ffffffff") {
            $ldxgrid = "0";
        }
        $ldxgridd = hexdec($ldxgrid) * 2;
        $con = $con + 8;
        $dxgrid = substr($lee, $con, $ldxgridd);
        $dxgridd = hex2bin($dxgrid);
        $con = $con + $ldxgridd;
        $watchdog = substr($lee, $con, 2);
        $watchdogd = hexdec($watchdog);
        $datamode = $moded;
        $datafreq = substr($freqd, 0, -3);
        $datacall = $decalld;
        $soft = $idd;
        $datagrid = $degridd;
        $portrx = $port;
        $ipft = $from;
        socket_close($socket);
        echo " [OK]\n\r";
        echo " Soft : $soft\n\r";
        echo " Call : $datacall\n\r";
        echo " Grid : $datagrid\n\r";
        echo " Mode : $datamode\n\r";
        echo " Freq : $datafreq\n\r";
        $isRaspberryPi = false;
        echo fg($mica, 5);
        if (stripos(PHP_OS, 'Linux') !== false) {
            if (is_readable('/sys/firmware/devicetree/base/model')) {
                $model = trim(file_get_contents('/sys/firmware/devicetree/base/model'));
                if (stripos($model, 'Raspberry Pi') !== false) {
                    echo " -----> It's a Raspberry Pi running Linux.\n\r";
                    $isRaspberryPi = true;
                } else {
                    echo " -----> It's Linux, but doesn't seem to be a Raspberry Pi.\n\r";
                }
            } else {
                echo " -----> It's Linux, but couldn't verify if it's be a Raspberry Pi.\n\r";
            }
        } else {
            echo " -----> It's not a Linux operating system, probably not a Raspberry Pi.\n\r";
        }
        if ($isRaspberryPi) {
            echo fg("$robot Active sudo without a password.", 3);
            echo fg("$robot LED control will be activated", 2);
            $led = true;
            $command1 = 'sudo sh -c "echo none > /sys/class/leds/ACT/trigger"';
            $command2 = 'sudo sh -c "echo none > /sys/class/leds/PWR/trigger"';
            shell_exec($command1);
            shell_exec($command2);
            $ledvoff = 'sudo sh -c "echo 0 > /sys/class/leds/ACT/brightness"';
            $ledvon = 'sudo sh -c "echo 1 > /sys/class/leds/ACT/brightness"';
            $ledroff = 'sudo sh -c "echo 0 > /sys/class/leds/PWR/brightness"';
            $ledron = 'sudo sh -c "echo 1 > /sys/class/leds/PWR/brightness"';
        } else {
            echo fg("$robot LED control will not be activated", 4);
            $led = false;
        }
        goto contr;
    }
}
socket_close($socket);

tdoce:
$datos = hex2bin($lee);
$datosa = procqso($datos);
$datosa = procqso($datos);
$datosb = genadi($datosa);
$datosc = $datosb[0];
$qsodata = qsotovar($datosa[0]);
// 使用返回的数组中的call值
$call = isset($qsodata['call']) ? $qsodata['call'] : '';
file_put_contents($adix, $datosc . "\n", FILE_APPEND);
global $contents; // 确保$contents是全局变量
if (!empty($call)) {
    $contents .= $call . " ";
}
echo fg("$robot $soft Register a contact in log for $dxc", 10);
goto trama;

// 批量处理解码信号
process_batch:

// 检查是否已达到批量处理时间间隔
$current_time = time();
if (($current_time - $last_batch_process_time) < $batch_process_interval) {
    goto trama;  // 如果未达到处理间隔，继续等待
}

// 重置上次处理时间
$last_batch_process_time = $current_time;

if (!empty($decoded_signals_buffer)) {
    echo fg("🔄 批量处理 " . count($decoded_signals_buffer) . " 个信号 (15秒内)", 6);
    
    // 记录批量处理日志
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] BATCH PROCESSING: Processing " . count($decoded_signals_buffer) . " signals\n";
    file_put_contents('batch_processing.log', $log_entry, FILE_APPEND | LOCK_EX);
    
    // 使用AI模型对整个批次进行分析和决策
    global $ollama_decision_maker;
    
    // 按时间分组处理信号
    $signals_by_time = array();
    foreach ($decoded_signals_buffer as $signal) {
        $time_key = $signal['time'];
        if (!isset($signals_by_time[$time_key])) {
            $signals_by_time[$time_key] = array();
        }
        $signals_by_time[$time_key][] = $signal;
    }
    
    // 对每个时间点的信号进行AI分析
    foreach ($signals_by_time as $time_key => $signals_at_time) {
        if (in_array($time_key, $last_time_processed)) {
            continue;  // 跳过已处理的时间
        }
        
        // 找出最值得响应的信号
        $best_signal = null;
        $best_priority = -1;
        
        foreach ($signals_at_time as $signal) {
            $dxcc_info = $signal['dxcc_info'];
            $band_info = $signal['band_info'];
            
            if ($dxcc_info && $dxcc_info['id'] != 'unknown') {
                // 使用AI进行决策
                $should_cq = false;
                $priority = 0;
                
                if (isset($ollama_decision_maker)) {
                    $ollama_available = $ollama_decision_maker->getAnalyzer()->isAvailable();
                    if ($ollama_available) {
                        try {
                            $should_cq = $ollama_decision_maker->shouldCQForDXCC(
                                $dxcc_info,
                                $band_info,
                                $signal['mode'],
                                $signal['freq'],
                                $signal['snr'],
                                $signal['time'],
                                $signals_at_time
                            );
                            
                            // 根据DXCC稀有度和通联状态设定优先级
                            if ($should_cq) {
                                $priority = 10; // AI推荐的信号
                                if (in_array($dxcc_info['id'], [24, 199, 197, 169, 249, 277])) {
                                    $priority = 100; // 极稀有DXCC
                                } elseif (!isset($worked_dxcc[$dxcc_info['id']])) {
                                    $priority = 50; // 新DXCC
                                }
                            }
                        } catch (Exception $e) {
                            // 如果AI处理失败，使用传统逻辑
                            $never_worked_global = !isset($worked_dxcc[$dxcc_info['id']]);
                            $worked_in_band = false;
                            if ($band_info && isset($worked_dxcc_bands[$band_info][$dxcc_info['id']])) {
                                $worked_in_band = true;
                            }
                            
                            $in_dxcc_whitelist = false;
                            if (!empty($dxcc_whitelist) && $dxcc_info['id']) {
                                $in_dxcc_whitelist = in_array($dxcc_info['id'], array_keys($dxcc_whitelist));
                            }
                            
                            $in_band_whitelist = false;
                            if (!empty($band_whitelist) && $band_info && $dxcc_info['id']) {
                                if (isset($band_whitelist[$band_info])) {
                                    $in_band_whitelist = in_array($dxcc_info['id'], $band_whitelist[$band_info]);
                                }
                            }
                            
                            if ($never_worked_global) {
                                $priority = 50;
                                $should_cq = true;
                            } elseif ($in_dxcc_whitelist && !$worked_in_band) {
                                $priority = 30;
                                $should_cq = true;
                            } elseif ($in_band_whitelist && !$worked_in_band) {
                                $priority = 20;
                                $should_cq = true;
                            } else {
                                $should_cq = false;
                            }
                        }
                    } else {
                        // Ollama不可用时，使用传统逻辑
                        $never_worked_global = !isset($worked_dxcc[$dxcc_info['id']]);
                        $worked_in_band = false;
                        if ($band_info && isset($worked_dxcc_bands[$band_info][$dxcc_info['id']])) {
                            $worked_in_band = true;
                        }
                        
                        $in_dxcc_whitelist = false;
                        if (!empty($dxcc_whitelist) && $dxcc_info['id']) {
                            $in_dxcc_whitelist = in_array($dxcc_info['id'], array_keys($dxcc_whitelist));
                        }
                        
                        $in_band_whitelist = false;
                        if (!empty($band_whitelist) && $band_info && $dxcc_info['id']) {
                            if (isset($band_whitelist[$band_info])) {
                                $in_band_whitelist = in_array($dxcc_info['id'], $band_whitelist[$band_info]);
                            }
                        }
                        
                        if ($never_worked_global) {
                            $priority = 50;
                        } elseif ($in_dxcc_whitelist && !$worked_in_band) {
                            $priority = 30;
                        } elseif ($in_band_whitelist && !$worked_in_band) {
                            $priority = 20;
                        }
                        
                        $should_cq = ($priority > 0);
                    }
                }
                
                if ($should_cq && $priority > $best_priority) {
                    $best_priority = $priority;
                    $best_signal = $signal;
                }
            }
        }
        
        // 如果找到最佳信号，执行响应
        if ($best_signal) {
            $lin = explode(" ", $best_signal['message']);
            if (sizeof($lin) == 4) {
                unset($lin[1]);
                $lin = array_values($lin);
            }
            
            $searchfor = $lin[1];
            $dxcc_info = $best_signal['dxcc_info'];
            $band_info = $best_signal['band_info'];
            
            // 设置响应参数
            $time = $best_signal['time'];
            $snrd = $best_signal['snr'];
            $moded = $best_signal['mode'];
            $deltafd = $best_signal['deltaf'];
            $messaged = $best_signal['message'];
            $qio = $best_signal['dxcc_name'];
            
            // 显示最佳信号
            $modedx = trim($moded);
            if ($modedx == "`") $modedx = "FST4";
            if ($modedx == "+") $modedx = "FT4";
            if ($modedx == "~") $modedx = "FT8";
            if ($modedx == "$") $modedx = "JT4";
            if ($modedx == "@") $modedx = "JT9";
            if ($modedx == "#") $modedx = "JT65";
            if ($modedx == ":") $modedx = "Q65";
            if ($modedx == "&") $modedx = "MSK144";
            
            $timed = str_pad(substr($time, 0, 6), 6);
            $snrd = str_pad(substr($snrd, 0, 3), 3);
            $deltafd = str_pad(substr($deltafd, 0, 4), 4);
            $moded = str_pad(substr($moded, 0, 4), 4);
            $messaged = str_pad(substr($messaged, 0, 20), 20);
            $zz = ">>";
            $qio = str_pad(substr($qio, 0, 20), 20);
            $modedx = str_pad(substr($modedx, 0, 6), 6);
            
            $band_display = $band_info ? "[$band_info]" : "";
            $priority_reason = $best_priority >= 100 ? "ULTRA RARE" : ($best_priority >= 50 ? "NEW DXCC" : ($best_priority >= 30 ? "GLOBAL WL" : "BAND WL"));
            
            $qq = "$timed  $snrd  $deltafd  $modedx  $zz $messaged - $qio $band_display [$priority_reason]";
            echo fg($qq, 2);
            
            // 设置要响应的DXCC
            $dxc = $lin[1];
            $sendcq = "1";
            $zz = "   ";
            
            echo fg("$robot I see @ $dxc in $qio", 9);
            $tempo = strtotime("now");
            $tempu = $tempo + 90;
            
            // 添加到已处理时间列表
            $last_time_processed[] = $time_key;
            if (count($last_time_processed) > 100) { // 防止列表无限增长
                $last_time_processed = array_slice($last_time_processed, -50);
            }
        }
        
        // 如果处理了当前时间的信号，跳出循环
        if ($best_signal) {
            break;
        }
    }
    
    // 清空缓冲区
    $decoded_signals_buffer = array();
} else {
    // 即使没有信号，也要检查是否需要响应正在进行的CQ
    if ($sendcq == "1" && time() > $tempu) {
        echo fg("$robot $dxc Not respond to the call", 5);
        $exclu[$dxc] = $dxc;
        $dxc = "";
        goto toch;
    }
}

goto trama;

// 存储解码信号到缓冲区
store_signal:
$mess = rtrim($messaged);
$lin = explode(" ", $mess);
if (sizeof($lin) == 4) {
    unset($lin[1]);
    $lin = array_values($lin);
}

// 获取当前波段信息
$band_info = "";
if (isset($freqd)) {
    if ($freqd >= 1800000 && $freqd < 2000000) $band_info = "160m";
    else if ($freqd >= 3500000 && $freqd < 4000000) $band_info = "80m";
    else if ($freqd >= 7000000 && $freqd < 7300000) $band_info = "40m";
    else if ($freqd >= 10100000 && $freqd < 10150000) $band_info = "30m";
    else if ($freqd >= 14000000 && $freqd < 14350000) $band_info = "20m";
    else if ($freqd >= 18068000 && $freqd < 18168000) $band_info = "17m";
    else if ($freqd >= 21000000 && $freqd < 21450000) $band_info = "15m";
    else if ($freqd >= 24890000 && $freqd < 24990000) $band_info = "12m";
    else if ($freqd >= 28000000 && $freqd < 29700000) $band_info = "10m";
}

// 检查是否在白名单中
$dxcc_info = locate($lin[1]);
$dxcc_id = $dxcc_info ? $dxcc_info['id'] : null;

// 检查DXCC白名单
$in_dxcc_whitelist = false;
if (!empty($dxcc_whitelist) && $dxcc_id) {
    $in_dxcc_whitelist = in_array($dxcc_id, array_keys($dxcc_whitelist));
}

// 检查波段白名单
$in_band_whitelist = false;
if (!empty($band_whitelist) && $band_info && $dxcc_id) {
    if (isset($band_whitelist[$band_info])) {
        $in_band_whitelist = in_array($dxcc_id, $band_whitelist[$band_info]);
    }
}

// 检查各种状态
$never_worked_global = false;
$worked_in_band = false;

if ($dxcc_id) {
    $never_worked_global = !isset($worked_dxcc[$dxcc_id]);  // 从未通联过（全局）
    if ($band_info && isset($worked_dxcc_bands[$band_info][$dxcc_id])) {
        $worked_in_band = true;  // 该波段已通联
    }
}

// 存储信号到缓冲区
$signal_data = array(
    'call' => $lin[1],
    'message' => $messaged,
    'dxcc_info' => $dxcc_info,
    'dxcc_name' => $dxcc_info ? $dxcc_info['name'] : 'unknown',
    'band_info' => $band_info,
    'freq' => $freqd,
    'snr' => $snrd,
    'time' => $timed,
    'mode' => $moded,
    'deltaf' => $deltafd,
    'in_dxcc_whitelist' => $in_dxcc_whitelist,
    'in_band_whitelist' => $in_band_whitelist,
    'never_worked_global' => $never_worked_global,
    'worked_in_band' => $worked_in_band
);

// 仅存储有意义的信号（CQ、73、RR73、RRR）
if (sizeof($lin) >= 2 && ($lin[0] == "CQ" || (isset($lin[2]) && ($lin[2] == "73" || $lin[2] == "RR73" || $lin[2] == "RRR")))) {
    $decoded_signals_buffer[] = $signal_data;
}

// 限制缓冲区大小，避免内存溢出
if (count($decoded_signals_buffer) > 100) {
    $decoded_signals_buffer = array_slice($decoded_signals_buffer, -50); // 保留最近50个信号
}

$rxrx = $rxrx + 1;
$tdx = "0";
goto trama;
?>