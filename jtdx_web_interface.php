<?php
/*
 * JTDX Web Interface - Enhanced Version with DXCC Targeting
 * 
 * This file creates a web interface that mimics JTDX's functionality,
 * displaying received messages and allowing CQ operations similar to robot_dxcc.php
 *
 * Created by: 心流 CLI
 * Enhancement: Web interface for JTDX with robot_dxcc capabilities
 */
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ULTRON - JTDX Web Interface</title>
    <link rel="stylesheet" href="jtdx_web_style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>ULTRON - JTDX Web Interface</h1>
            <div class="status-bar">
                <div class="status-item">状态: <span id="connection-status">未连接</span></div>
                <div class="status-item">软件: <span id="software-name">-</span></div>
                <div class="status-item">呼号: <span id="de-call">-</span></div>
                <div class="status-item">模式: <span id="mode">-</span></div>
                <div class="status-item">频段: <span id="band">-</span></div>
                <div class="status-item">CQ状态: <span id="cq-status">停止</span></div>
            </div>
        </header>

        <div class="control-panel">
            <div class="controls">
                <button id="start-btn" class="btn btn-primary">开始监听</button>
                <button id="stop-btn" class="btn btn-danger" disabled>停止监听</button>
                <button id="send-cq-btn" class="btn btn-success" disabled>发送CQ</button>
                <button id="stop-cq-btn" class="btn btn-warning" disabled>停止CQ</button>
                <button id="refresh-btn" class="btn btn-secondary">刷新</button>
            </div>
        </div>

        <div class="main-content">
            <div class="decodes-panel">
                <h2>解码信息</h2>
                <div class="table-container">
                    <table id="decodes-table">
                        <thead>
                            <tr>
                                <th>时间</th>
                                <th>SNR</th>
                                <th>频偏</th>
                                <th>模式</th>
                                <th>状态</th>
                                <th>消息</th>
                                <th>DXCC</th>
                                <th>波段</th>
                                <th>优先级</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="decodes-body">
                            <!-- 动态内容将通过JavaScript填充 -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="stats-panel">
                <h2>统计信息</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>今日QSO</h3>
                        <div class="stat-value" id="today-qso">0</div>
                    </div>
                    <div class="stat-card">
                        <h3>当前CQ</h3>
                        <div class="stat-value" id="current-cq">-</div>
                    </div>
                    <div class="stat-card">
                        <h3>新DXCC</h3>
                        <div class="stat-value" id="new-dxcc">0</div>
                    </div>
                    <div class="stat-card">
                        <h3>白名单</h3>
                        <div class="stat-value" id="whitelist-count">0</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="log-panel">
            <h2>系统日志</h2>
            <div class="log-container">
                <pre id="log-output"></pre>
            </div>
        </div>
    </div>

    <script src="jtdx_web_script.js"></script>
</body>
</html>