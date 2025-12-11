#!/usr/bin/env php
<?php
/*
 * JTDX Web Interface Startup Script
 * Starts the PHP built-in server for the JTDX web interface
 */

echo "ULTRON - JTDX Web Interface\n";
echo "Starting PHP built-in server...\n\n";

$port = 8000;
$address = 'localhost';

echo "Server will be available at: http://{$address}:{$port}/jtdx_web_interface.php\n";
echo "Press Ctrl+C to stop the server\n\n";

// 启动PHP内置服务器
$command = "php -S {$address}:{$port} -t " . escapeshellarg(getcwd());
echo "Executing: {$command}\n\n";

system($command);
?>