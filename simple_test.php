<?php
// 简化版ULTRON连接测试
error_reporting(0);
date_default_timezone_set("UTC");

echo "##############################################################################\n";
echo " Created by Eduardo Castillo - LU9DCE\n";
echo " (C) 2023 - castilloeduardo@outlook.com.ar\n";
echo "##############################################################################\n";
echo " -----< ULTRON : Preparing : Version LR-230925\n";
echo " Looking for radio software wait ...\n";

// 尝试监听所有接口上的UDP数据
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$socket) {
    die("无法创建UDP套接字: " . socket_strerror(socket_last_error()) . "\n");
}

if (!socket_bind($socket, '0.0.0.0', 2237)) {
    echo "无法绑定到端口2237: " . socket_strerror(socket_last_error()) . "\n";
    socket_close($socket);
    exit(1);
}

echo "监听端口2237上的JTDX/WSJT-X数据...\n";

// 等待接收数据包
while (true) {
    $from = '';
    $port = 0;
    $bytes = socket_recvfrom($socket, $buffer, 512, 0, $from, $port);
    if ($bytes !== false) {
        echo "从 $from:$port 接收到数据包\n";
        $lee = bin2hex($buffer);
        $type = substr($lee, 16, 8);
        
        echo "数据包类型: $type\n";
        
        // 检查是否是状态包 (type 00000001)
        if ($type == "00000001") {
            echo "检测到JTDX/WSJT-X状态包\n";
            
            // 解析数据包
            $magic = substr($lee, 0, 8);
            $ver = substr($lee, 8, 8);
            $largoid = substr($lee, 24, 8);
            $larg = hexdec($largoid) * 2;
            $id = substr($lee, 32, $larg);
            $idd = hex2bin($id);
            $soft = $idd;
            
            echo "软件名称: $soft\n";
            echo "连接成功！接收到的数据包类型为状态包 (0x00000001)\n";
            echo "这表明JTDX/WSJT-X正在运行并且可以与ULTRON通信\n";
            
            socket_close($socket);
            exit(0);
        } else {
            echo "接收到其他类型的数据包: $type\n";
        }
    }
}
?>