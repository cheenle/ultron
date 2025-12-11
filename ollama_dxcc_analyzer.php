<?php
/**
 * Ollama DXCC分析器
 * 使用本地ollama qwen3:4b模型进行DXCC新子头/波段的智能判断
 */

class OllamaDXCCAnalyzer {
    private $api_url;
    private $model;
    private $timeout;
    
    public function __construct($api_url = 'http://localhost:11434/api/generate', $model = 'qwen3:4b') {
        $this->api_url = $api_url;
        $this->model = $model;
        $this->timeout = 30; // 30秒超时
    }
    
    /**
     * 向ollama发送请求并获取响应
     */
    public function sendRequest($prompt) {
        $timestamp = date('Y-m-d H:i:s');
        $data = array(
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
            'options' => array(
                'temperature' => 0.1,  // 降低温度以获得更一致的结果
                'top_p' => 0.9,
                'max_tokens' => 200
            )
        );
        
        $json_data = json_encode($data);
        
        // 记录请求日志
        $log_entry = "[$timestamp] OLLAMA REQUEST:\nModel: {$this->model}\nPrompt: $prompt\n\n";
        file_put_contents('ollama_requests.log', $log_entry, FILE_APPEND | LOCK_EX);
        
        $context = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => $json_data,
                'timeout' => $this->timeout
            )
        ));
        
        $result = file_get_contents($this->api_url, false, $context);
        
        if ($result === false) {
            $error_log = "[$timestamp] OLLAMA ERROR: 无法连接到Ollama服务\n\n";
            file_put_contents('ollama_requests.log', $error_log, FILE_APPEND | LOCK_EX);
            throw new Exception('无法连接到Ollama服务，请确保ollama正在运行');
        }
        
        $response = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_log = "[$timestamp] OLLAMA ERROR: JSON解析错误 - " . substr($result, 0, 200) . "...\n\n";
            file_put_contents('ollama_requests.log', $error_log, FILE_APPEND | LOCK_EX);
            throw new Exception('Ollama响应JSON解析错误');
        }
        
        if (!isset($response['response'])) {
            $error_log = "[$timestamp] OLLAMA ERROR: 响应格式错误 - " . substr($result, 0, 200) . "...\n\n";
            file_put_contents('ollama_requests.log', $error_log, FILE_APPEND | LOCK_EX);
            throw new Exception('Ollama响应格式错误: ' . $result);
        }
        
        // 记录响应日志
        $response_log = "[$timestamp] OLLAMA RESPONSE:\nResponse: {$response['response']}\n\n";
        file_put_contents('ollama_requests.log', $response_log, FILE_APPEND | LOCK_EX);
        
        return $response['response'];
    }
    
    /**
     * 判断是否应该对某个DXCC实体进行CQ操作
     */
    public function shouldCQForDXCC($dxcc_info, $current_band = null, $current_mode = null, $worked_dxcc = array(), $whitelist_dxcc = array()) {
        $dxcc_id = $dxcc_info['id'];
        $dxcc_name = $dxcc_info['name'];
        $dxcc_flag = $dxcc_info['flag'] ?? 'N/A';
        
        // 构建提示词
        $prompt = $this->buildCQPrompt($dxcc_id, $dxcc_name, $dxcc_flag, $current_band, $current_mode, $worked_dxcc, $whitelist_dxcc);
        
        try {
            $response = $this->sendRequest($prompt);
            
            // 解析模型响应
            $decision = $this->parseModelResponse($response);
            
            // 记录日志
            $this->logDecision($dxcc_id, $dxcc_name, $current_band, $decision, $response);
            
            return $decision;
            
        } catch (Exception $e) {
            // 如果模型调用失败，使用默认逻辑
            echo "⚠️ Ollama模型调用失败: " . $e->getMessage() . "\n";
            echo "  使用默认逻辑: " . $dxcc_name . " (ID: $dxcc_id)\n";
            
            // 默认逻辑：如果是未通联的白名单实体，则进行CQ
            $is_whitelisted = isset($whitelist_dxcc[$dxcc_id]);
            $is_worked = isset($worked_dxcc[$dxcc_id]);
            
            return $is_whitelisted && !$is_worked;
        }
    }
    
    /**
     * 构建CQ决策的提示词
     */
    private function buildCQPrompt($dxcc_id, $dxcc_name, $dxcc_flag, $current_band, $current_mode, $worked_dxcc, $whitelist_dxcc) {
        // 准备已通联和白名单列表
        $worked_list = empty($worked_dxcc) ? "无" : implode(", ", array_slice($worked_dxcc, 0, 10));
        $whitelist_list = empty($whitelist_dxcc) ? "无" : implode(", ", array_slice($whitelist_dxcc, 0, 10));
        
        // 获取DXCC稀有度信息（根据ID的大小或其他指标）
        $dxcc_rarity = $this->estimateDXCCRarity($dxcc_id);
        
        $prompt = "你是一个专业的DXCC通联机器人，负责判断是否应该对某个DXCC实体进行CQ操作。

当前环境:
- 当前波段: " . ($current_band ? $current_band : '未知') . "
- 当前模式: " . ($current_mode ? $current_mode : '未知') . "
- 已通联DXCC实体: {$worked_list}
- 待通联白名单实体: {$whitelist_list}

待评估的DXCC实体:
- ID: {$dxcc_id}
- 名称: {$dxcc_name}
- 旗帜: {$dxcc_flag}
- 稀有度: {$dxcc_rarity}

请进行以下分析:
1. 该实体是否已在通联列表中？
2. 该实体是否在白名单中？
3. 当前波段/模式下该实体的通联价值如何？
4. 考虑到通联难度和稀有度，是否值得进行CQ操作？

请严格按照以下JSON格式回复:
{
  \"decision\": true/false,
  \"confidence\": \"high/medium/low\",
  \"reason\": \"简短的理由\",
  \"rarity\": \"rare/common/normal\"
}

要求：
- 如果实体已在通联列表中，decision必须为false
- 如果实体不在白名单中，通常decision为false（除非特别有价值）
- 如果实体在白名单中且未通联，通常decision为true
- confidence表示模型对决策的信心度
- reason提供简短的决策理由
- rarity表示该实体的稀有程度

现在请分析并给出你的决策:";

        return $prompt;
    }
    
    /**
     * 估算DXCC实体的稀有度
     */
    private function estimateDXCCRarity($dxcc_id) {
        // 这里可以根据DXCC ID或其他标准来估算稀有度
        // 这是一个简化的逻辑，可以根据实际数据进行调整
        $rare_ids = [24, 199, 246, 155, 237, 229, 197, 169, 249, 277, 513, 71]; // 一些特殊或稀有的DXCC ID
        $very_rare_ids = [24, 199, 197, 169, 155]; // 极其稀有的DXCC ID
        
        if (in_array($dxcc_id, $very_rare_ids)) {
            return "very rare";
        } elseif (in_array($dxcc_id, $rare_ids)) {
            return "rare";
        } else {
            return "common";
        }
    }
    
    /**
     * 解析模型响应
     */
    private function parseModelResponse($response) {
        // 尝试直接解析JSON
        $json_start = strpos($response, '{');
        $json_end = strrpos($response, '}');
        
        if ($json_start !== false && $json_end !== false) {
            $json_str = substr($response, $json_start, $json_end - $json_start + 1);
            $data = json_decode($json_str, true);
            
            if ($data && isset($data['decision'])) {
                return array(
                    'decision' => (bool)$data['decision'],
                    'confidence' => $data['confidence'] ?? 'medium',
                    'reason' => $data['reason'] ?? '模型分析',
                    'rarity' => $data['rarity'] ?? 'normal'
                );
            }
        }
        
        // 如果无法解析JSON，使用正则表达式提取关键信息
        $decision = false;
        if (preg_match('/decision["\s:]+\s*(true|false)/i', $response, $matches)) {
            $decision = strtolower($matches[1]) === 'true';
        } elseif (preg_match('/should.*cq/i', $response) && !preg_match('/should not/i', $response)) {
            $decision = true;
        }
        
        return array(
            'decision' => $decision,
            'confidence' => 'low',
            'reason' => '基于文本分析',
            'rarity' => 'normal'
        );
    }
    
    /**
     * 记录决策日志
     */
    private function logDecision($dxcc_id, $dxcc_name, $band, $decision, $response) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] Ollama DXCC Decision: $dxcc_name (ID: $dxcc_id) [Band: $band] -> " . 
                    ($decision['decision'] ? 'CQ' : 'SKIP') . 
                    " (Confidence: {$decision['confidence']}, Reason: {$decision['reason']})\n";
        
        file_put_contents('ollama_dxcc_decisions.log', $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 检查Ollama服务是否可用
     */
    public function isAvailable() {
        try {
            $data = array(
                'model' => $this->model,
                'prompt' => 'Hello',
                'stream' => false
            );
            
            $json_data = json_encode($data);
            
            $context = stream_context_create(array(
                'http' => array(
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => $json_data,
                    'timeout' => 5
                )
            ));
            
            $result = file_get_contents($this->api_url, false, $context);
            return $result !== false;
            
        } catch (Exception $e) {
            return false;
        }
    }
}
?>