<?php
/**
 * Ollama增强的DXCC CQ决策器
 * 集成到现有的DXCC系统中，使用AI模型进行智能CQ判断
 */

require_once 'ollama_dxcc_analyzer.php';
require_once 'ollama_dxcc_prompt_engineering.php';
require_once 'whitelist_manager.php';

class OllamaDXCCDecisionMaker {
    private $analyzer;
    private $prompt_engineer;
    private $whitelist_manager;
    private $worked_cache;
    
    public function __construct($whitelist_manager) {
        $this->analyzer = new OllamaDXCCAnalyzer();
        $this->prompt_engineer = new OllamaDXCCPromptEngineering($this->analyzer);
        $this->whitelist_manager = $whitelist_manager;
        
        // 加载已通联的DXCC缓存
        $this->worked_cache = $this->loadWorkedCache();
    }
    
    /**
     * 加载已通联DXCC缓存
     */
    private function loadWorkedCache() {
        if (!file_exists('dxcc_worked_cache.json')) {
            return array();
        }
        
        $worked_json = file_get_contents('dxcc_worked_cache.json');
        $worked = json_decode($worked_json, true);
        
        if (!is_array($worked)) {
            return array();
        }
        
        return $worked;
    }
    
    /**
     * 判断是否应该对特定DXCC实体进行CQ操作
     */
    public function shouldCQForDXCC($dxcc_info, $current_band = null, $current_mode = null, $current_freq = null, $snr = null, $time = null, $all_decoded_signals = array()) {
        $dxcc_id = $dxcc_info['id'];
        $dxcc_name = $dxcc_info['name'];
        
        // 检查是否已通联
        if ($this->isWorked($dxcc_id)) {
            echo "🔍 $dxcc_name (ID: $dxcc_id) 已通联，跳过CQ\n";
            return false;
        }
        
        // 检查是否在白名单中
        $is_whitelisted = $this->isInWhitelist($dxcc_id, $current_band);
        if (!$is_whitelisted) {
            echo "🔍 $dxcc_name (ID: $dxcc_id) 不在白名单中，跳过CQ\n";
            return false;
        }
        
        // 使用Ollama模型进行智能判断
        if ($this->analyzer->isAvailable()) {
            echo "🤖 使用Ollama模型分析 $dxcc_name (ID: $dxcc_id) 的CQ决策...\n";
            
            // 根据不同情况选择合适的提示词
            if (!empty($all_decoded_signals)) {
                // 有多个解码信号，使用新DXCC发现逻辑
                $prompt = $this->prompt_engineer->generateNewDXCCPrompt(
                    $dxcc_info, 
                    $current_freq, 
                    $current_band, 
                    $current_mode, 
                    $snr, 
                    $time, 
                    $all_decoded_signals
                );
                
                $response = $this->analyzer->sendRequest($prompt);
                $decision = $this->parseNewDXCCResponse($response);
                
                if ($decision['immediate_response']) {
                    echo "🎯 AI建议立即响应 $dxcc_name: {$decision['reason']}\n";
                    return true;
                } else {
                    echo "❌ AI建议跳过 $dxcc_name: {$decision['reason']}\n";
                    return false;
                }
            } else {
                // 使用标准CQ判断逻辑
                $decision = $this->analyzer->shouldCQForDXCC(
                    $dxcc_info, 
                    $current_band, 
                    $current_mode, 
                    $this->worked_cache, 
                    $this->getWhitelistAsArray()
                );
                
                if ($decision['decision']) {
                    echo "✅ AI建议CQ $dxcc_name: {$decision['reason']} (置信度: {$decision['confidence']})\n";
                    // 记录决策日志
                    $timestamp = date('Y-m-d H:i:s');
                    $log_entry = "[$timestamp] AI DECISION: CQ $dxcc_name (ID: $dxcc_id) - {$decision['reason']} (Confidence: {$decision['confidence']})\n";
                    file_put_contents('ollama_dxcc_decisions.log', $log_entry, FILE_APPEND | LOCK_EX);
                    return true;
                } else {
                    echo "❌ AI建议跳过 $dxcc_name: {$decision['reason']} (置信度: {$decision['confidence']})\n";
                    // 记录决策日志
                    $timestamp = date('Y-m-d H:i:s');
                    $log_entry = "[$timestamp] AI DECISION: SKIP $dxcc_name (ID: $dxcc_id) - {$decision['reason']} (Confidence: {$decision['confidence']})\n";
                    file_put_contents('ollama_dxcc_decisions.log', $log_entry, FILE_APPEND | LOCK_EX);
                    return false;
                }
            }
        } else {
            // 如果Ollama不可用，使用传统逻辑
            echo "⚠️ Ollama不可用，使用传统白名单逻辑\n";
            return $is_whitelisted && !$this->isWorked($dxcc_id);
        }
    }
    
    /**
     * 判断波段特定的CQ策略
     */
    public function getBandSpecificCQStrategy($dxcc_info, $current_band, $current_mode, $time, $propagation_conditions = array(), $recent_cq_signals = array()) {
        if (!$this->analyzer->isAvailable()) {
            // 如果模型不可用，返回基本策略
            return array(
                'cq_strategy' => 'immediate',
                'optimal_time_window' => '00:00-23:59',
                'confidence' => 'low'
            );
        }
        
        $prompt = $this->prompt_engineer->generateBandSpecificPrompt(
            $dxcc_info,
            $current_band,
            $current_mode,
            $time,
            $propagation_conditions,
            $recent_cq_signals
        );
        
        if (!$this->analyzer->isAvailable()) {
            // 如果Ollama不可用，返回基本策略
            return array(
                'cq_strategy' => 'immediate',
                'optimal_time_window' => '00:00-23:59',
                'confidence' => 'low'
            );
        }
        
        $response = $this->analyzer->sendRequest($prompt);
        $strategy = $this->parseBandStrategyResponse($response);
        
        return $strategy;
    }
    
    /**
     * 处理多个竞争DXCC实体的决策
     */
    public function handleCompetingDXCCs($target_dxcc, $competing_dxccs, $current_band, $current_mode, $available_time_slots) {
        if (empty($competing_dxccs) || !$this->analyzer->isAvailable()) {
            // 如果没有竞争实体或模型不可用，直接返回目标实体
            return $target_dxcc;
        }
        
        $prompt = $this->prompt_engineer->generateCompetitionDecisionPrompt(
            $target_dxcc,
            $competing_dxccs,
            $current_band,
            $current_mode,
            $available_time_slots
        );
        
        if (!$this->analyzer->isAvailable()) {
            // 如果Ollama不可用，返回目标实体
            return $target_dxcc;
        }
        
        $response = $this->analyzer->sendRequest($prompt);
        $decision = $this->parseCompetitionResponse($response);
        
        // 根据优先级排序返回最高优先级的实体
        if (!empty($decision['priority_ranking'])) {
            $top_priority_id = $decision['priority_ranking'][0];
            
            // 查找对应的DXCC实体
            if ($top_priority_id == $target_dxcc['id']) {
                return $target_dxcc;
            }
            
            foreach ($competing_dxccs as $dxcc) {
                if ($dxcc['id'] == $top_priority_id) {
                    return $dxcc;
                }
            }
        }
        
        // 默认返回目标实体
        return $target_dxcc;
    }
    
    /**
     * 解析新DXCC发现的响应
     */
    private function parseNewDXCCResponse($response) {
        $json_start = strpos($response, '{');
        $json_end = strrpos($response, '}');
        
        if ($json_start !== false && $json_end !== false) {
            $json_str = substr($response, $json_start, $json_end - $json_start + 1);
            $data = json_decode($json_str, true);
            
            if ($data && isset($data['immediate_response'])) {
                return array(
                    'immediate_response' => (bool)$data['immediate_response'],
                    'priority_level' => $data['priority_level'] ?? 'medium',
                    'reason' => $data['reason'] ?? 'AI分析结果',
                    'time_sensitivity' => $data['time_sensitivity'] ?? 'normal'
                );
            }
        }
        
        // 如果无法解析JSON，使用默认行为
        return array(
            'immediate_response' => true,
            'priority_level' => 'medium',
            'reason' => '默认响应',
            'time_sensitivity' => 'normal'
        );
    }
    
    /**
     * 解析波段策略响应
     */
    private function parseBandStrategyResponse($response) {
        $json_start = strpos($response, '{');
        $json_end = strrpos($response, '}');
        
        if ($json_start !== false && $json_end !== false) {
            $json_str = substr($response, $json_start, $json_end - $json_start + 1);
            $data = json_decode($json_str, true);
            
            if ($data && isset($data['cq_strategy'])) {
                return array(
                    'cq_strategy' => $data['cq_strategy'] ?? 'immediate',
                    'optimal_time_window' => $data['optimal_time_window'] ?? '00:00-23:59',
                    'band_specific_tips' => $data['band_specific_tips'] ?? '',
                    'confidence' => $data['confidence'] ?? 'medium'
                );
            }
        }
        
        // 默认策略
        return array(
            'cq_strategy' => 'immediate',
            'optimal_time_window' => '00:00-23:59',
            'band_specific_tips' => '',
            'confidence' => 'low'
        );
    }
    
    /**
     * 解析竞争决策响应
     */
    private function parseCompetitionResponse($response) {
        $json_start = strpos($response, '{');
        $json_end = strrpos($response, '}');
        
        if ($json_start !== false && $json_end !== false) {
            $json_str = substr($response, $json_start, $json_end - $json_start + 1);
            $data = json_decode($json_str, true);
            
            if ($data && isset($data['priority_ranking'])) {
                return array(
                    'priority_ranking' => $data['priority_ranking'],
                    'time_allocation' => $data['time_allocation'] ?? array(),
                    'strategy' => $data['strategy'] ?? 'focus_selective',
                    'reasoning' => $data['reasoning'] ?? 'AI优先级分析'
                );
            }
        }
        
        // 默认返回空数组
        return array(
            'priority_ranking' => array(),
            'time_allocation' => array(),
            'strategy' => 'focus_selective',
            'reasoning' => '默认竞争分析'
        );
    }
    
    /**
     * 检查DXCC是否已通联
     */
    private function isWorked($dxcc_id) {
        return isset($this->worked_cache[$dxcc_id]);
    }
    
    /**
     * 检查DXCC是否在白名单中
     */
    private function isInWhitelist($dxcc_id, $band = null) {
        return $this->whitelist_manager->isInWhitelist($dxcc_id, $band);
    }
    
    /**
     * 获取白名单数组格式
     */
    private function getWhitelistAsArray() {
        return $this->whitelist_manager->loadWhitelist();
    }
    
    /**
     * 获取Ollama分析器实例
     */
    public function getAnalyzer() {
        return $this->analyzer;
    }
}
?>