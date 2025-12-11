#!/usr/bin/env php
<?php
/*
 * JTDX Web Interface Startup Script
 * Starts the PHP built-in server for the JTDX web interface
 * Supports IPv6 and external access
 */

echo "ULTRON - JTDX Web Interface\n";
echo "Starting PHP built-in server (IPv6/External enabled)...\n\n";

$port = 8000;
$address = '::'; // IPv6 all interfaces (equivalent to 0.0.0.0 for IPv4)

echo "Server will be available at:\n";
echo "  IPv4: http://0.0.0.0:{$port}/jtdx_web_interface.php\n";
echo "  IPv6: http://[::]:{$port}/jtdx_web_interface.php\n";
echo "  Local: http://localhost:{$port}/jtdx_web_interface.php\n";
echo "Press Ctrl+C to stop the server\n\n";

// 启动PHP内置服务器，绑定到所有接口
$command = "php -S [::]:{$port} -t " . escapeshellarg(getcwd());
echo "Executing: {$command}\n\n";

system($command);
?>