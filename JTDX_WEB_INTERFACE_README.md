# ULTRON - JTDX Web Interface

这是一个用于JTDX/WSJT-X的Web界面，提供类似JTDX的界面和功能，同时集成了robot_dxcc的DXCC目标功能。

## 功能特性

- 实时显示JTDX/WSJT-X解码信息
- 类似JTDX的界面风格和颜色编码
- CQ操作控制（开始/停止）
- DXCC目标识别和优先级标记
- 白名单管理
- 统计信息显示
- 支持IPv6和外网访问

## 目录结构

- `jtdx_web_interface.php` - 主Web界面
- `jtdx_web_style.css` - 界面样式
- `jtdx_web_script.js` - 前端JavaScript逻辑
- `jtdx_api.php` - 后端API接口
- `start_jtdx_web.php` - 本地启动脚本
- `start_jtdx_web_ipv6.php` - IPv6/外网启动脚本

## 启动方法

### 方法1：本地访问（仅限本机）
```bash
php start_jtdx_web.php
```

### 方法2：IPv6和外网访问
```bash
php start_jtdx_web_ipv6.php
```

### 方法3：直接使用PHP内置服务器（本地）
```bash
php -S localhost:8000 -t .
```

### 方法4：直接使用PHP内置服务器（IPv6/外网）
```bash
php -S [::]:8000 -t .
```

## 访问地址

- 本地访问: http://localhost:8000/jtdx_web_interface.php
- IPv4外网访问: http://[服务器IP]:8000/jtdx_web_interface.php
- IPv6外网访问: http://[服务器IPv6地址]:8000/jtdx_web_interface.php
- IPv6本地访问: http://[::1]:8000/jtdx_web_interface.php

## 使用说明

1. 确保robot_dxcc.php和相关配置文件已正确设置
2. 启动Web界面服务器
3. 在浏览器中打开界面
4. 点击"开始监听"按钮
5. 选择要CQ的呼号，然后点击"CQ"按钮
6. 使用"发送CQ"按钮开始CQ操作

## 注意事项

- 此Web界面需要与正在运行的JTDX/WSJT-X软件配合使用
- 需要确保UDP端口2237的通信正常
- 某些功能可能需要robot_dxcc.php的完整环境
- 外网访问时，请确保防火墙已开放相应端口
- IPv6访问时，可能需要在浏览器中使用方括号包围IPv6地址

## 集成功能

- 与robot_dxcc.php的DXCC白名单功能集成
- 支持多种优先级标记（NEW DXCC, GLOBAL WL, BAND WL等）
- 实时状态更新和统计信息