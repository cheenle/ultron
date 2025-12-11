<?php
echo "=== UDP数据接收测试工具 ===\n\n";

// 测试参数
$test_port = 2237;
$test_host = '0.0.0.0';  // 监听所有接口

// 创建UDP套接字
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$socket) {
    die("无法创建套接字: " . socket_strerror(socket_last_error()) . "\n");
}

// 设置套接字选项
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);

// 绑定到指定端口
echo "正在绑定到 {$test_host}:{$test_port}...\n";
if (!socket_bind($socket, $test_host, $test_port)) {
    die("绑定失败: " . socket_strerror(socket_last_error($socket)) . "\n");
}

echo "✅ 绑定成功，正在监听UDP数据...\n";
echo "请确保JTDX/WSJT-X的UDP服务器设置为:\n";
echo "  - 地址: 192.168.1.64 (或广播地址)\n";
echo "  - 端口: 2237\n";
echo "  - 协议: UDP\n\n";
echo "等待数据... (按Ctrl+C停止)\n\n";

// 非阻塞模式
socket_set_nonblock($socket);
$timeout = 5; // 5秒超时
echo "将在5秒内显示接收到的数据...\n";

$start_time = time();
$data_received = false;

while (time() - $start_time < $timeout) {
    $buffer = '';
    $from = '';
    $port = 0;
    
    $bytes_received = @socket_recvfrom($socket, $buffer, 4096, 0, $from, $port);
    
    if ($bytes_received !== false && $bytes_received > 0) {
        $data_received = true;
        echo "📡 收到数据来自 {$from}:{$port}\n";
        echo "📊 数据长度: {$bytes_received} 字节\n";
        echo "🔍 原始数据 (前100字节): " . bin2hex(substr($buffer, 0, 100)) . "\n";
        
        // 尝试解析WSJT-X协议头部
        if (strlen($buffer) >= 24) {
            $magic = substr($buffer, 0, 4);
            $type = substr($buffer, 8, 4);
            echo "🎯 Magic: " . bin2hex($magic) . "\n";
            echo "📋 Type: " . bin2hex($type) . "\n";
        }
        echo "---\n";
    }
    
    usleep(100000); // 100ms延迟
}

if (!$data_received) {
    echo "⚠️  在5秒内未收到任何UDP数据\n";
    echo "可能的原因:\n";
    echo "  1. JTDX/WSJT-X未启动或未配置UDP服务器\n";
    echo "  2. 防火墙阻止了UDP端口2237\n";
    echo "  3. 网络配置问题\n";
    echo "  4. JTDX/WSJT-X的UDP服务器地址配置错误\n\n";
    echo "请检查JTDX/WSJT-X的UDP设置:\n";
    echo "  - 打开JTDX/WSJT-X\n";
    echo "  - 进入菜单: File → Settings → Reporting\n";
    echo "  - 勾选'Enable UDP Server'\n";
    echo "  - 设置UDP Server地址为: 192.168.1.64\n";
    echo "  - 设置端口为: 2237\n";
    echo "  - 点击'OK'保存\n";
}

socket_close($socket);
echo "\n测试完成。\n";
?>