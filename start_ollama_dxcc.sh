#!/bin/bash

# 启动ollama服务并运行AI增强的DXCC系统（批量处理模式）

echo "🚀 启动Ollama服务..."

# 检查ollama是否已在运行
if pgrep -f "ollama serve" > /dev/null; then
    echo "✅ Ollama服务已在运行"
else
    # 启动ollama服务
    ollama serve > /dev/null 2>&1 &
    OLLAMA_PID=$!
    echo "✅ Ollama服务已启动 (PID: $OLLAMA_PID)"
    
    # 等待ollama服务启动
    echo "⏳ 等待Ollama服务启动..."
    sleep 5
    
    # 检查ollama是否成功启动
    if kill -0 $OLLAMA_PID 2>/dev/null; then
        echo "✅ Ollama服务启动成功"
    else
        echo "⚠️  Ollama服务启动可能失败，请检查是否已安装ollama"
    fi
fi

# 检查qwen3:4b模型是否已下载
echo "🔍 检查qwen3:4b模型..."
if ollama list | grep -q "qwen3:4b"; then
    echo "✅ qwen3:4b模型已存在"
else
    echo "⏳ qwen3:4b模型不存在，正在下载..."
    ollama pull qwen3:4b
    if [ $? -eq 0 ]; then
        echo "✅ qwen3:4b模型下载完成"
    else
        echo "⚠️  qwen3:4b模型下载失败，系统将以传统模式运行"
    fi
fi

echo "🚀 启动AI增强的DXCC系统（批量处理模式）..."
echo "💡 按 Ctrl+C 停止程序"
echo "💡 系统将每15秒批量处理一次解码信号"

# 运行AI增强的DXCC系统
php robot_dxcc_enhanced_ollama.php