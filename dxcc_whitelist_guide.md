# ULTRON DXCC白名单功能使用说明

## 概述
此增强版ULTRON程序提供了DXCC白名单功能，帮助您更有效地获取新的DXCC实体。程序会分析您的通联日志，识别未通联的DXCC实体，并优先响应这些实体的CQ呼叫。

## 主要功能

1. **DXCC白名单**: 设置特定的DXCC实体，程序会优先响应这些实体的CQ
2. **波段白名单**: 为特定波段设置DXCC实体，优先在该波段通联这些实体
3. **未通联实体识别**: 自动识别您尚未通联的DXCC实体
4. **波段特定通联**: 识别您尚未在特定波段通联的DXCC实体

## 配置文件

### dxcc_config.php
这是主要的配置文件，包含两个数组：

1. **$dxcc_whitelist**: 全局DXCC白名单
   - 格式: `"DXCC_ID" => "DXCC名称"`
   - 这些实体在任何波段都会被优先考虑

2. **$band_whitelist**: 按波段的DXCC白名单
   - 格式: `"波段" => array("DXCC_ID1", "DXCC_ID2", ...)`
   - 这些实体仅在指定波段被优先考虑

## 使用方法

### 1. 分析当前通联情况
```bash
php -c extra/php-lnx.ini dxcc_analyzer.php
```
此脚本会分析您的wsjtx_log.adi文件，显示:
- 已通联的DXCC实体
- 按波段的通联情况
- 未通联的DXCC实体
- 推荐的白名单设置

### 2. 配置白名单
编辑dxcc_config.php文件，根据分析结果设置您想要优先通联的DXCC实体。

### 3. 运行增强版ULTRON
```bash
php -c extra/php-lnx.ini robot_dxcc.php
```

## 输出说明
在程序输出中:
- 显示"**[WHITELIST]**"表示该通联在白名单中
- 显示波段信息如"**[20m]**"

## 配置示例

```php
// 优先通联稀有DXCC实体
$dxcc_whitelist = array(
    "246" => "SOV MILITARY ORDER OF MALTA",  // 马耳他主权军事骑士团
    "260" => "MONACO",                       // 摩纳哥
    "207" => "RODRIGUEZ IS",                 // 罗德里格斯岛
);

// 在特定波段优先通联某些DXCC实体
$band_whitelist = array(
    "20m" => array("246", "260", "207"),     // 20米波段
    "40m" => array("246", "260", "4"),       // 40米波段
);
```

## 提示
1. 定期运行dxcc_analyzer.php来更新您的通联状态
2. 根据传播条件调整波段白名单
3. 可以为特殊活动或比赛调整白名单
4. 稀有DXCC实体通常在特定时间或波段出现，可以根据这些规律设置白名单