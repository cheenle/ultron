<?php
// JTDX/WSJT-X连接和功能测试脚本
error_reporting(E_ALL);
echo "正在测试JTDX/WSJT-X与ULTRON的连接...\n";

// 1. 检查必要的PHP模块
$required_modules = ['sockets', 'json', 'pcntl', 'posix', 'mbstring', 'openssl'];
$missing_modules = [];
foreach ($required_modules as $module) {
    if (!extension_loaded($module)) {
        $missing_modules[] = $module;
    }
}
if (empty($missing_modules)) {
    echo "✓ 所有必需的PHP模块都已安装\n";
} else {
    echo "✗ 缺少PHP模块: " . implode(', ', $missing_modules) . "\n";
    exit(1);
}

// 2. 检查必要的文件
$required_files = ['robot.php', 'base.json', 'wsjtx_log.adi'];
$missing_files = [];
foreach ($required_files as $file) {
    if (!file_exists($file)) {
        $missing_files[] = $file;
    }
}
if (empty($missing_files)) {
    echo "✓ 所有必需的文件都存在\n";
} else {
    echo "✗ 缺少文件: " . implode(', ', $missing_files) . "\n";
    exit(1);
}

// 3. 测试UDP端口连接
echo "测试UDP端口2237...\n";
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if ($socket) {
    if (socket_bind($socket, '0.0.0.0', 2237)) {
        echo "✓ 成功绑定到UDP端口2237\n";
        
        // 设置非阻塞模式并尝试接收数据
        socket_set_nonblock($socket);
        
        $start_time = time();
        $data_received = false;
        echo "等待JTDX/WSJT-X数据...\n";
        while ((time() - $start_time) < 5 && !$data_received) {
            $from = '';
            $port = 0;
            $bytes = socket_recvfrom($socket, $buffer, 512, 0, $from, $port);
            if ($bytes !== false && $bytes > 0) {
                echo "✓ 从 $from:$port 接收到 $bytes 字节的数据\n";
                $data_received = true;
                
                // 解析数据包类型
                $hex_data = bin2hex($buffer);
                $type = substr($hex_data, 16, 8);
                if ($type == "00000001") {
                    echo "✓ 检测到JTDX/WSJT-X状态数据包\n";
                    
                    // 解析软件名称
                    $largoid = substr($hex_data, 24, 8);
                    $larg = hexdec($largoid) * 2;
                    $id_start = 32;
                    $id_hex = substr($hex_data, $id_start, $larg);
                    $soft_name = hex2bin($id_hex);
                    echo "✓ 运行的软件: $soft_name\n";
                } else {
                    echo "✓ 接收到数据包类型: $type\n";
                }
            }
            usleep(200000); // 0.2秒
        }
        if (!$data_received) {
            echo "- 在5秒内没有接收到JTDX/WSJT-X数据\n";
            echo "  请确保JTDX/WSJT-X正在运行并已启用UDP服务器功能\n";
        }
        socket_close($socket);
    } else {
        echo "✗ 无法绑定到UDP端口2237: " . socket_strerror(socket_last_error()) . "\n";
        echo "  端口可能被其他程序(如RUMlogNG)占用\n";
        socket_close($socket);
        exit(1);
    }
} else {
    echo "✗ 无法创建UDP套接字: " . socket_strerror(socket_last_error()) . "\n";
    exit(1);
}

echo "\n=== ULTRON功能测试完成 ===\n";
echo "✓ PHP环境配置正确\n";
echo "✓ 必需的模块和文件存在\n";
echo "✓ UDP端口连接正常\n";
echo "✓ 可以接收JTDX/WSJT-X数据(如果软件正在运行)\n";
echo "\nULTRON已准备好运行！\n";
?>