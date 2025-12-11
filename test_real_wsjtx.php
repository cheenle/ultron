<?php
echo "=== 真实WSJT-X UDP数据包测试 ===\n\n";

// 目标配置
$target_ip = '127.0.0.1';  // 本地测试
$target_port = 2237;

// 创建UDP套接字
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$socket) {
    die("无法创建套接字: " . socket_strerror(socket_last_error()) . "\n");
}

// 模拟真实的WSJT-X状态包 (类型 0x00000001 - 状态包)
// 格式: Magic(4) + Schema(4) + Type(4) + Id Length(4) + Id + 数据
$magic = 0xADBCCDA6;  // WSJT-X Magic number
$schema = 3;          // Schema版本  
$type = 1;            // 类型: 状态包
$id_length = 4;       // ID长度
$id = "JTDX";         // 软件ID

// 构建完整的WSJT-X状态包
$packet = pack('N', $magic) .      // Magic number (4字节)
          pack('N', $schema) .     // Schema version (4字节) 
          pack('N', $type) .       // Packet type (4字节)
          pack('N', $id_length) .  // ID length (4字节)
          $id .                    // ID (4字节)
          // 添加一些状态数据
          pack('v', 1) .           // 状态: 正在解码
          pack('V', time()) .      // 时间戳
          pack('V', 14074000) .    // 频率: 14.074MHz
          pack('v', 3) .           // 模式长度
          "FT8" .                  // 模式
          pack('v', 8) .           // 呼号长度
          "BG1SB" .                // 呼号
          pack('v', 4) .           // Grid长度
          "ON80";                  // Grid

echo "发送真实WSJT-X数据包到 {$target_ip}:{$target_port}\n";
echo "数据包长度: " . strlen($packet) . " 字节\n";
echo "数据包内容 (hex): " . bin2hex($packet) . "\n\n";

// 发送数据
$result = socket_sendto($socket, $packet, strlen($packet), 0, $target_ip, $target_port);

if ($result !== false) {
    echo "✅ 数据包发送成功！发送了 {$result} 字节\n";
} else {
    echo "❌ 发送失败: " . socket_strerror(socket_last_error($socket)) . "\n";
}

socket_close($socket);

// 同时发送广播包
$socket2 = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_set_option($socket2, SOL_SOCKET, SO_BROADCAST, 1);

$broadcast_ip = '192.168.1.255';
$result2 = socket_sendto($socket2, $packet, strlen($packet), 0, $broadcast_ip, $target_port);

if ($result2 !== false) {
    echo "✅ 广播数据包发送成功到 {$broadcast_ip}:{$target_port}\n";
} else {
    echo "❌ 广播发送失败: " . socket_strerror(socket_last_error($socket2)) . "\n";
}

socket_close($socket2);

echo "\n测试完成。请检查ULTRON日志。\n";
?>