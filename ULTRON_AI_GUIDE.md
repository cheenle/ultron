# 使用说明：AI增强的DXCC CQ系统

## 概述

`robot_dxcc_enhanced_ollama.php` 是一个AI增强版的DXCC通联系统，集成了本地Ollama qwen3:4b模型来智能判断CQ操作。在Ollama不可用时，系统会自动回退到传统的白名单逻辑。

## 启动AI增强系统

### 1. 启动Ollama服务（可选，但推荐）

```bash
# 启动Ollama服务
ollama serve &

# 拉取qwen3:4b模型
ollama pull qwen3:4b
```

### 2. 运行AI增强的DXCC系统

```bash
php robot_dxcc_enhanced_ollama.php
```

## 系统特性

### AI决策逻辑
- **稀有DXCC实体**：自动识别并优先处理稀有DXCC实体
- **波段优化**：根据当前波段调整CQ策略
- **智能过滤**：结合白名单系统和AI分析进行智能判断
- **回退机制**：Ollama不可用时自动使用传统逻辑

### 输出标记说明
- `[AI-RECOMMENDED]` - AI模型建议进行CQ操作
- `[NEW DXCC ACTIVITY!]` - 检测到新DXCC实体活动
- `[WL]` - 该实体在白名单中
- `[NEW DXCC]` - 全局未通联过的DXCC实体（最高优先级）

## 系统要求

- PHP 7.0+
- Ollama（可选，用于AI功能）
- qwen3:4b模型（可选，用于AI功能）

## 故障排除

如果系统闪退，请检查：
1. 确保所有依赖文件存在：`base.json`, `whitelist_manager.php`, `wsjtx_log.adi`
2. 确保PHP版本兼容性
3. 检查系统资源是否充足

AI功能不可用是正常的，系统会自动回退到传统逻辑继续运行。

## 传统模式

如果不想使用AI功能，可以继续使用原始的：
- `robot_dxcc.php`
- `robot_dxcc_enhanced.php`