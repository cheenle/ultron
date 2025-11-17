<?php
/*
 * ULTRON - Enhanced Version with DXCC Targeting
 *
 * Created by: LU9DCE
 * Copyright: 2023 Eduardo Castillo
 * Contact: castilloeduardo@outlook.com.ar
 * License: Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International
 *
 * Enhanced by: 心流 CLI
 * Enhancement: DXCC targeting functionality
 */
error_reporting(0);
date_default_timezone_set("UTC");
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
$version = "LR-230925-DXCC";
$portrx = "";
$filename = __DIR__ . '/wsjtx_log.adi';

// 加载DXCC配置文件
if (file_exists('dxcc_config.php')) {
    include 'dxcc_config.php';
    
    // 如果配置文件中没有定义，设置默认值
    if (!isset($dxcc_whitelist_only)) {
        $dxcc_whitelist_only = 0;  // 默认为0（优先模式），设置为1则只响应白名单
    }
    if (!isset($dxcc_whitelist)) {
        $dxcc_whitelist = array();
    }
    if (!isset($band_whitelist)) {
        $band_whitelist = array();
    }
} else {
    // 默认设置
    $dxcc_whitelist_only = 0;  // 默认为0（优先模式）
    $dxcc_whitelist = array();
    $band_whitelist = array();
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

if (!file_exists($filename)) {
    file_put_contents($filename, '');
}
$adix = realpath($filename);

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

// 分析日志文件，识别已通联的DXCC实体
$cotcot = 0;
$contents = "";
$worked_dxcc = array(); // 已通联的DXCC实体
$worked_dxcc_bands = array(); // 按波段记录已通联的DXCC实体

$archivoEntrada = fopen($adix, 'r');
while (($linea = fgets($archivoEntrada)) !== false) {
    if (strpos($linea, '<eor>') !== false || strpos($linea, '<EOR>') !== false) {
        $linea = procqso($linea);
        $linea = qsotovar($linea[0]);
        $contents .= $call;
        $cotcot++;
        
        // 根据呼号前缀识别DXCC实体
        $dxcc_info = locate($call);
        if ($dxcc_info && isset($dxcc_info['id'])) {
            $dxcc_id = $dxcc_info['id'];
            $worked_dxcc[$dxcc_id] = $dxcc_info['name'];
            
            // 记录按波段的通联情况
            if (isset($band)) {
                if (!isset($worked_dxcc_bands[$band])) {
                    $worked_dxcc_bands[$band] = array();
                }
                $worked_dxcc_bands[$band][$dxcc_id] = $dxcc_info['name'];
            }
        }
    }
}
fclose($archivoEntrada);

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
    return $sendcq = "1";
}

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
                    $clean_prefix = preg_replace('/[\/\(].*$/', '', $prefix);
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
goto ptex;

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

ptex:
$mess = rtrim($messaged);
$lin = explode(" ", $mess);
$zz = "   ";
$fg = "8";
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

// 如果启用白名单专有模式，只响应白名单中的DXCC
if ($dxcc_whitelist_only == 1) {
    // 在白名单中且未在当前波段通联过的，优先响应
    $worked_in_band = false;
    if ($band_info && $dxcc_id && isset($worked_dxcc_bands[$band_info][$dxcc_id])) {
        $worked_in_band = true;
    }
    
    if ((($in_dxcc_whitelist || $in_band_whitelist) && !$worked_in_band) && sizeof($lin) == 3 && $lin[1] != $decalld && $sendcq == "0" && ($lin[0] == "CQ" || $lin[2] == "73" || $lin[2] == "RR73" || $lin[2] == "RRR")) {
        $zz = ">>";
        $fg = "2";
    } else {
        // 如果不是白名单中的DXCC，不响应
        $zz = "##"; // 标记为不响应
        $fg = "8";
    }
} else {
    // 优先白名单模式
    $worked_in_band = false;
    if ($band_info && $dxcc_id && isset($worked_dxcc_bands[$band_info][$dxcc_id])) {
        $worked_in_band = true;
    }
    
    if ((($in_dxcc_whitelist || $in_band_whitelist) && !$worked_in_band) && sizeof($lin) == 3 && $lin[1] != $decalld && $sendcq == "0" && ($lin[0] == "CQ" || $lin[2] == "73" || $lin[2] == "RR73" || $lin[2] == "RRR")) {
        $zz = ">>";
        $fg = "2";
    } else if (strpos($contents, $searchfor) === false && sizeof($lin) == 3 && $lin[1] != $decalld && $sendcq == "0" && ($lin[0] == "CQ" || $lin[2] == "73" || $lin[2] == "RR73" || $lin[2] == "RRR")) {
        $zz = ">>";
        $fg = "2";
    }
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

if ($led) {
    shell_exec($ledvon);
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

// 如果在白名单中，添加特殊标记
if (($in_dxcc_whitelist || $in_band_whitelist) && !$worked_in_band) {
    $qq .= " [WHITELIST]";
}

if ($led) {
    shell_exec($ledvoff);
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

if ($lin[0] != $decalld && $lin[0] != "CQ" && $lin[1] == $dxc && ($lin[2] != "73" || $lin[2] != "RR73")) {
    echo fg("$robot Busy?", 4);
    $dxc = "";
    goto toch;
}
if ($lin[0] == $decalld && $lin[2] == "73") {
    echo fg("$robot Qso confirmed successfully", 10);
    $mega = $mega + 1;
    $sendcq = "0";
    $tempo = "0000";
    $tempu = "0000";
    
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
qsotovar($datosa[0]);
file_put_contents($adix, $datosc . "\n", FILE_APPEND);
$contents .= $call . " ";
echo fg("$robot $soft Register a contact in log for $dxc", 10);
goto trama;
?>