# Ollama AI增强的DXCC CQ系统

## 系统概述

此系统使用本地的Ollama qwen3:4b模型来增强DXCC通联决策，通过AI分析来判断是否对新发现的DXCC实体进行CQ操作。

## 系统组件

1. `ollama_dxcc_analyzer.php` - Ollama API调用和响应处理
2. `ollama_dxcc_prompt_engineering.php` - AI提示词工程和策略生成
3. `ollama_dxcc_decision_maker.php` - AI决策逻辑和集成
4. `robot_dxcc_enhanced_ollama.php` - AI增强的主机器人程序
5. `test_ollama_dxcc.php` - 系统测试脚本

## 安装和配置

### 1. 安装Ollama

```bash
# 下载并安装Ollama (macOS)
curl -fsSL https://ollama.ai/install.sh | sh

# 启动Ollama服务
ollama serve
```

### 2. 拉取qwen3:4b模型

```bash
ollama pull qwen3:4b
```

### 3. 验证配置

```bash
# 运行测试脚本检查系统
php test_ollama_dxcc.php
```

## 运行系统

### 启动AI增强的DXCC系统

```bash
php robot_dxcc_enhanced_ollama.php
```

系统将:
- 检查Ollama服务可用性
- 加载现有的DXCC白名单
- 使用AI模型分析新发现的DXCC实体
- 根据AI决策进行CQ操作

## AI决策逻辑

系统使用以下因素进行CQ决策:

1. **DXCC实体稀有度** - 基于DXCC ID和其他因素
2. **通联状态** - 是否已通联过该实体
3. **白名单状态** - 是否在白名单中
4. **波段活动** - 当前波段下的实体活跃度
5. **信号质量** - 解码信号的SNR等参数
6. **竞争实体** - 当前频率上的其他DXCC实体

## 输出说明

在解码输出中，您会看到:

- `[AI-RECOMMENDED]` - AI建议CQ的实体
- `[NEW DXCC ACTIVITY!]` - 发现新DXCC实体活动
- `[WL]` - 在白名单中的实体

## 故障排除

### Ollama服务不可用
- 确保`ollama serve`正在运行
- 检查端口11434是否可用
- 确认qwen3:4b模型已下载

### AI决策不准确
- 系统会在`ollama_dxcc_decisions.log`中记录所有决策
- 可以调整提示词工程以优化决策逻辑

### 性能问题
- AI请求可能需要几秒钟处理时间
- 可以调整AI模型参数以平衡准确性和速度