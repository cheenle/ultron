# Ollama AI增强的DXCC CQ系统

## 概述

这个系统通过集成本地Ollama qwen3:4b模型，增强了原有的DXCC通联系统。AI模型分析新发现的DXCC实体，根据多种因素（如实体稀有度、通联状态、波段活动等）智能判断是否进行CQ操作。

## 核心功能

- **AI决策**：使用qwen3:4b模型进行DXCC CQ决策
- **稀有度分析**：识别并优先处理稀有DXCC实体
- **波段优化**：根据不同波段调整CQ策略
- **实时分析**：在解码时实时分析DXCC实体
- **白名单集成**：与现有白名单系统无缝集成

## 文件结构

- `ollama_dxcc_analyzer.php` - Ollama API接口
- `ollama_dxcc_prompt_engineering.php` - 提示词工程
- `ollama_dxcc_decision_maker.php` - AI决策逻辑
- `robot_dxcc_enhanced_ollama.php` - 主程序
- `test_ollama_dxcc.php` - 测试脚本
- `OLLAMA_DXCC_GUIDE.md` - 使用说明

## 运行

```bash
# 启动Ollama服务
ollama serve &

# 运行AI增强系统
php robot_dxcc_enhanced_ollama.php
```

## 优势

1. **智能决策**：AI模型根据多种因素做出更精准的CQ决策
2. **效率提升**：减少对低优先级实体的响应，专注于高价值目标
3. **动态适应**：根据当前波段和频率条件调整策略
4. **无缝集成**：与现有系统完全兼容，可随时切换回传统模式