<?php
/*
 * JTDX Web Interface API
 * Provides API endpoints for the JTDX web interface to interact with robot_dxcc functionality
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 引入共享数据处理
require_once 'jtdx_shared_data.php';

// 引入robot_dxcc中的必要函数（防止输出到浏览器）
if (file_exists('robot_dxcc.php')) {
    // 为了防止主逻辑运行，我们只提取函数定义而不执行
    $content = file_get_contents('robot_dxcc.php');
    
    // 临时缓冲区以避免输出
    ob_start();
    
    // 简单提取关键函数
    if (preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\([^)]*\)\s*\{(?:[^{}]+|\{[^{}]*\})*\}/', $content, $matches)) {
        foreach ($matches[0] as $function) {
            // 仅包含我们需要的函数
            if (strpos($function, 'procqso') !== false || 
                strpos($function, 'locate') !== false || 
                strpos($function, 'get_dxcc_info_by_id') !== false ||
                strpos($function, 'vicen') !== false) {
                // 用一个特殊标记包装函数以避免直接执行
                eval(str_replace('function', 'function _api_', $function));
            }
        }
    }
    
    // 恢复输出缓冲区
    ob_end_clean();
    
    // 重命名函数以供API使用
    if (function_exists('_api_procqso')) {
        function procqso($data) { return _api_procqso($data); }
    }
    if (function_exists('_api_locate')) {
        function locate($licrx) { return _api_locate($licrx); }
    }
    if (function_exists('_api_get_dxcc_info_by_id')) {
        function get_dxcc_info_by_id($dxcc_id) { return _api_get_dxcc_info_by_id($dxcc_id); }
    }
    if (function_exists('_api_vicen')) {
        function vicen($licencia) { return _api_vicen($licencia); }
    }
} else {
    // 定义模拟函数
    function procqso($data) {
        return [];
    }
    
    function locate($licrx) {
        return ['id' => 'unknown', 'flag' => '❌', 'name' => 'Unknown'];
    }
    
    function get_dxcc_info_by_id($dxcc_id) {
        return null;
    }
    
    function vicen($licencia) {
        return false;
    }
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'send_cq':
        handleSendCQ();
        break;
    case 'get_status':
        handleGetStatus();
        break;
    case 'get_decodes':
        handleGetDecodes();
        break;
    case 'stop_cq':
        handleStopCQ();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function handleSendCQ() {
    global $jtdx_shared_data;
    
    $call = $_POST['call'] ?? '';
    
    if (empty($call)) {
        echo json_encode(['success' => false, 'error' => 'Call is required']);
        return;
    }
    
    // 更新共享状态
    $jtdx_shared_data->updateStatus([
        'cq_active' => true,
        'current_target' => $call
    ]);
    
    // 记录日志
    error_log("ULTRON: Sending CQ to $call");
    
    echo json_encode([
        'success' => true, 
        'message' => "CQ started for $call",
        'target_call' => $call
    ]);
}

function handleStopCQ() {
    global $jtdx_shared_data;
    
    // 更新共享状态
    $jtdx_shared_data->updateStatus([
        'cq_active' => false,
        'current_target' => ''
    ]);
    
    error_log("ULTRON: CQ stopped");
    
    echo json_encode([
        'success' => true, 
        'message' => "CQ stopped"
    ]);
}

function handleGetStatus() {
    global $jtdx_shared_data;
    
    $status = $jtdx_shared_data->getStatus();
    
    echo json_encode($status);
}

function handleGetDecodes() {
    global $jtdx_shared_data;
    
    $decodes = $jtdx_shared_data->getLatestDecodes(50);
    
    echo json_encode(['decodes' => $decodes]);
}

// 如果是直接运行此文件（非包含），则输出错误
if (!isset($jtdx_web_api)) {
    // 不输出错误，因为我们希望API可以独立运行
    // echo json_encode(['error' => 'This file should not be accessed directly']);
}
?>