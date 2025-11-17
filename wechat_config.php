<?php
// 企业微信通知配置
// 请根据您的企业微信设置修改以下参数

// 企业微信配置
$wechat_config = array(
    'corp_id' => '',           // 企业ID - 从企业微信管理后台获取
    'corp_secret' => '',       // 应用Secret - 从应用详情页获取
    'agent_id' => '',          // 应用ID - 从应用详情页获取
    'to_user' => '@all',       // 接收消息的用户，默认@all发送给所有人
    'enabled' => false         // 是否启用微信通知，默认关闭
);

// 获取企业微信访问Token
function get_wechat_access_token($corp_id, $corp_secret) {
    $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid={$corp_id}&corpsecret={$corp_secret}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        if (isset($result['access_token'])) {
            return $result['access_token'];
        }
    }
    
    return false;
}

// 发送企业微信消息
function send_wechat_message($message, $config) {
    if (!$config['enabled'] || empty($config['corp_id']) || empty($config['corp_secret'])) {
        return false;
    }
    
    $access_token = get_wechat_access_token($config['corp_id'], $config['corp_secret']);
    if (!$access_token) {
        echo "获取微信访问Token失败\n";
        return false;
    }
    
    $url = "https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token={$access_token}";
    
    $data = array(
        'touser' => $config['to_user'],
        'msgtype' => 'text',
        'agentid' => $config['agent_id'],
        'text' => array(
            'content' => $message
        ),
        'safe' => 0
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $result = json_decode($response, true);
        if (isset($result['errcode']) && $result['errcode'] == 0) {
            echo "微信消息发送成功\n";
            return true;
        } else {
            echo "微信消息发送失败: " . ($result['errmsg'] ?? '未知错误') . "\n";
            return false;
        }
    }
    
    echo "微信API请求失败，HTTP代码: $http_code\n";
    return false;
}

// 格式化DXCC通知消息
function format_dxcc_notification($call, $dxcc_info, $band, $mode, $time) {
    $message = "🎉 新的DXCC通联！\n\n";
    $message .= "呼号: {$call}\n";
    $message .= "DXCC: {$dxcc_info['name']} ({$dxcc_info['id']})\n";
    $message .= "波段: {$band}\n";
    $message .= "模式: {$mode}\n";
    $message .= "时间: {$time}\n";
    
    if (isset($dxcc_info['flag'])) {
        $message .= "国旗: {$dxcc_info['flag']}\n";
    }
    
    return $message;
}

// 测试微信通知
function test_wechat_notification($config) {
    $test_message = "🔔 ULTRON微信通知测试\n\n";
    $test_message .= "如果您收到此消息，说明微信通知功能配置成功！\n";
    $test_message .= "时间: " . date('Y-m-d H:i:s') . "\n";
    $test_message .= "UTC: " . gmdate('Y-m-d H:i:s') . "\n";
    
    return send_wechat_message($test_message, $config);
}

?>