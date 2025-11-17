# ULTRON DXCC白名单功能使用说明 | DXCC Whitelist Guide

## 概述 | Overview
此增强版ULTRON程序提供了DXCC白名单功能，帮助您更有效地获取新的DXCC实体。程序会分析您的通联日志，识别未通联的DXCC实体，并优先响应这些实体的CQ呼叫。

This enhanced ULTRON program provides DXCC whitelist functionality to help you more effectively obtain new DXCC entities. The program analyzes your contact logs, identifies unworked DXCC entities, and prioritizes responding to CQ calls from these entities.

## 主要功能 | Key Features

1. **DXCC白名单**: 设置特定的DXCC实体，程序会优先响应这些实体的CQ | **DXCC Whitelist**: Set specific DXCC entities, the program will prioritize responding to CQ from these entities
2. **波段白名单**: 为特定波段设置DXCC实体，优先在该波段通联这些实体 | **Band Whitelist**: Set DXCC entities for specific bands, prioritize contacts on those bands
3. **未通联实体识别**: 自动识别您尚未通联的DXCC实体 | **Unworked Entity Identification**: Automatically identify DXCC entities you haven't worked
4. **波段特定通联**: 识别您尚未在特定波段通联的DXCC实体 | **Band-specific Contacts**: Identify DXCC entities you haven't worked on specific bands

## 配置文件 | Configuration Files

### dxcc_config.php (PHP版本)
这是主要的配置文件，包含两个数组：

1. **$dxcc_whitelist**: 全局DXCC白名单 | Global DXCC whitelist
   - 格式: `"DXCC_ID" => "DXCC名称"` | Format: `"DXCC_ID" => "DXCC_Name"`
   - 这些实体在任何波段都会被优先考虑 | These entities are prioritized on all bands

2. **$band_whitelist**: 按波段的DXCC白名单 | Band-specific DXCC whitelist
   - 格式: `"波段" => array("DXCC_ID1", "DXCC_ID2", ...)` | Format: `"Band" => array("DXCC_ID1", "DXCC_ID2", ...)`
   - 这些实体仅在指定波段被优先考虑 | These entities are only prioritized on specified bands

### dxcc_config.py (Python版本)
Python版本使用类似的配置结构：

```python
# 全局DXCC白名单 | Global DXCC whitelist
dxcc_whitelist = {
    "1": "USA",
    "110": "SPAIN", 
    "284": "BULGARIA"
}

# 按波段的白名单 | Band-specific whitelist
band_whitelist = {
    "20m": {"1": "USA", "110": "SPAIN"},
    "40m": {"1": "USA", "284": "BULGARIA"}
}

# 白名单模式 | Whitelist mode
dxcc_whitelist_only = False  # False=优先模式, True=仅白名单模式
```

## 使用方法 | Usage Methods

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