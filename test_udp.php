<?php
// 简化的JTDX/WSJT-X连接测试脚本
error_reporting(E_ALL);
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$socket) {
    die("无法创建UDP套接字: " . socket_strerror(socket_last_error()) . "\n");
}

// 尝试绑定到2237端口 - 注意：如果RUMlogNG正在使用此端口，这将失败
if (!socket_bind($socket, '0.0.0.0', 2237)) {
    echo "警告: 无法绑定到端口2237: " . socket_strerror(socket_last_error()) . "\n";
    echo "这可能是因为RUMlogNG或其他程序正在使用此端口\n";
    socket_close($socket);
    
    // 创建一个不同的端口来测试JTDX是否发送数据
    $test_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if (!$test_socket) {
        die("无法创建测试套接字\n");
    }
    
    // 使用一个临时端口来测试
    if (!socket_bind($test_socket, '127.0.0.1', 2238)) {
        die("无法绑定到测试端口2238: " . socket_strerror(socket_last_error()) . "\n");
    }
    
    echo "测试套接字创建成功，正在监听端口2238上的JTDX/WSJT-X数据...\n";
    echo "请注意，JTDX/WSJT-X需要配置为将UDP数据发送到127.0.0.1:2238\n";
    
    socket_set_nonblock($test_socket);
    $start = time();
    while ((time() - $start) < 10) {
        $from = '';
        $port = 0;
        $bytes = socket_recvfrom($test_socket, $buffer, 512, 0, $from, $port);
        if ($bytes !== false) {
            echo "从 $from:$port 接收到 $bytes 字节的数据\n";
            echo "十六进制数据: " . bin2hex($buffer) . "\n";
            socket_close($test_socket);
            exit(0);
        }
        usleep(500000); // 0.5秒
    }
    socket_close($test_socket);
    echo "在10秒内没有接收到任何数据\n";
} else {
    echo "成功绑定到端口2237，正在监听JTDX/WSJT-X数据...\n";
    socket_set_nonblock($socket);
    $start = time();
    while ((time() - $start) < 10) {
        $from = '';
        $port = 0;
        $bytes = socket_recvfrom($socket, $buffer, 512, 0, $from, $port);
        if ($bytes !== false) {
            echo "从 $from:$port 接收到 $bytes 字节的数据\n";
            echo "十六进制数据: " . bin2hex($buffer) . "\n";
            socket_close($socket);
            exit(0);
        }
        usleep(500000); // 0.5秒
    }
    socket_close($socket);
    echo "在10秒内没有接收到任何数据\n";
}
?>