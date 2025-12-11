<?php
echo "=== UDP数据发送测试工具 ===\n\n";

// 目标配置
$target_ip = '192.168.1.64';
$target_port = 2237;

// 创建UDP套接字
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$socket) {
    die("无法创建套接字: " . socket_strerror(socket_last_error()) . "\n");
}

// 设置广播选项
socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);

// 模拟WSJT-X的UDP数据包 - 这是一个简化的状态包
// WSJT-X UDP协议头部: Magic(4) + Schema(4) + Type(4) + Id Length(4) + Id + 数据
echo "正在发送模拟的WSJT-X UDP数据...\n";

// 创建一个简单的WSJT-X状态包 (类型 0x00000001 - 心跳包)
$magic = 0xADBCCDA6;  // WSJT-X Magic number
$schema = 3;          // Schema版本
$type = 1;            // 类型: 状态包
$id_length = 4;       // ID长度
$id = "JTDX";         // 软件ID

// 构建数据包
$data = pack('N', $magic) .      // Magic number (4字节)
         pack('N', $schema) .     // Schema version (4字节)
         pack('N', $type) .       // Packet type (4字节)
         pack('N', $id_length) .  // ID length (4字节)
         $id;                     // ID (4字节)

echo "发送数据到 {$target_ip}:{$target_port}\n";
echo "数据长度: " . strlen($data) . " 字节\n";
echo "数据内容 (hex): " . bin2hex($data) . "\n\n";

// 发送数据到单播地址
$result = socket_sendto($socket, $data, strlen($data), 0, $target_ip, $target_port);

// 同时发送广播包
$broadcast_ip = '192.168.1.255';
$result2 = socket_sendto($socket, $data, strlen($data), 0, $broadcast_ip, $target_port);

if ($result2 !== false) {
    echo "✅ 广播数据发送成功到 {$broadcast_ip}:{$target_port}\n";
} else {
    echo "❌ 广播发送失败: " . socket_strerror(socket_last_error($socket)) . "\n";
}

if ($result !== false) {
    echo "✅ 数据发送成功！发送了 {$result} 字节\n";
} else {
    echo "❌ 发送失败: " . socket_strerror(socket_last_error($socket)) . "\n";
}

socket_close($socket);
echo "\n测试完成。\n";
?>