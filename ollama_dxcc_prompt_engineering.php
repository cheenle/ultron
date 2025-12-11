<?php
/**
 * Ollama DXCC提示词工程
 * 专门处理DXCC新子头/波段的判断逻辑
 */

class OllamaDXCCPromptEngineering {
    private $analyzer;
    
    public function __construct($analyzer) {
        $this->analyzer = $analyzer;
    }
    
    /**
     * 生成新DXCC实体发现的提示词
     */
    public function generateNewDXCCPrompt($dxcc_info, $current_frequency, $current_band, $current_mode, $snr, $time, $all_decoded_signals) {
        $dxcc_id = $dxcc_info['id'];
        $dxcc_name = $dxcc_info['name'];
        $dxcc_flag = $dxcc_info['flag'] ?? 'N/A';
        
        // 分析当前频率上的活动情况
        $activity_summary = $this->analyzeFrequencyActivity($all_decoded_signals, $current_band);
        
        $prompt = "你是一个专业的DXCC通联机器人，专门负责识别和响应新DXCC实体的出现。

当前环境:
- 频率: {$current_frequency} MHz
- 波段: {$current_band}
- 模式: {$current_mode}
- 信号强度: {$snr} dB
- 时间: {$time}
- 当前频率活动摘要: {$activity_summary}

新发现的DXCC实体信息:
- DXCC ID: {$dxcc_id}
- DXCC名称: {$dxcc_name}
- 旗帜: {$dxcc_flag}

历史数据:
- 已通联DXCC数量: [系统会提供]
- 待通联白名单实体: [系统会提供]
- 该实体是否首次发现: [系统会提供]

请评估此DXCC实体的通联优先级并回答以下问题:

1. 这个实体是否值得立即响应？
2. 在当前条件下，通联成功的可能性有多大？
3. 与其他活动信号相比，这个信号的优先级如何？
4. 建议使用什么策略进行通联？

请严格按照以下JSON格式回复:
{
  \"immediate_response\": true/false,
  \"priority_level\": \"critical/high/medium/low\",
  \"success_probability\": \"very_high/high/medium/low/very_low\",
  \"cq_strategy\": \"immediate_cq/delayed_cq/monitor_only/ignore\",
  \"reason\": \"简短的决策理由\",
  \"time_sensitivity\": \"urgent/important/normal/low\"
}

评估标准:
- ID为24, 199, 197, 169的实体（Peter I Island, Bouvet Island等）具有最高优先级
- 新发现的实体比已知实体优先级高
- 信号强度良好的实体优先级更高
- 当前波段活跃的实体优先级更高
- 白名单中的实体优先级更高

请提供你的分析和决策:";
        
        return $prompt;
    }
    
    /**
     * 生成波段特定的CQ策略提示词
     */
    public function generateBandSpecificPrompt($dxcc_info, $current_band, $current_mode, $time, $propagation_conditions, $recent_cq_signals) {
        $dxcc_id = $dxcc_info['id'];
        $dxcc_name = $dxcc_info['name'];
        $dxcc_flag = $dxcc_info['flag'] ?? 'N/A';
        
        // 分析波段传播条件
        $prop_summary = $this->summarizePropagationConditions($propagation_conditions, $current_band);
        
        // 分析最近的CQ信号
        $cq_summary = $this->summarizeRecentCQSignals($recent_cq_signals, $current_band);
        
        $prompt = "你是一个专业的DXCC通联机器人，负责制定波段特定的CQ策略。

当前环境:
- 波段: {$current_band}
- 模式: {$current_mode}
- 时间: {$time}
- 传播条件: {$prop_summary}
- 最近CQ信号摘要: {$cq_summary}

目标DXCC实体:
- DXCC ID: {$dxcc_id}
- DXCC名称: {$dxcc_name}
- 旗帜: {$dxcc_flag}

波段特性:
- {$current_band}波段通常在{$time}有{$this->getBandCharacteristics($current_band, $time)}传播特性
- 该波段下此DXCC实体的活跃频率: [常见频率]
- 该波段下此DXCC实体的典型活动时间: [活动时间]

请制定针对此波段的CQ策略并回答:

1. 当前波段条件下，对此实体进行CQ是否合适？
2. 什么时间段最适合进行CQ？
3. 应该使用什么CQ策略？
4. 有什么波段特定的注意事项？

请严格按照以下JSON格式回复:
{
  \"band_appropriate\": true/false,
  \"optimal_time_window\": \"HH:MM-HH:MM\",
  \"cq_strategy\": \"immediate/scheduled/deferred/avoid\",
  \"cq_frequency\": \"common/rare/strategic\",
  \"band_specific_tips\": \"波段特定的建议\",
  \"confidence\": \"high/medium/low\"
}

要求:
- 考虑当前波段的传播特性
- 考虑目标实体在该波段的历史活动
- 考虑当前时间的传播条件
- 提供具体的CQ时机建议

请提供你的波段CQ策略:";
        
        return $prompt;
    }
    
    /**
     * 生成多实体竞争决策提示词
     */
    public function generateCompetitionDecisionPrompt($target_dxcc, $competing_dxccs, $current_band, $current_mode, $available_time_slots) {
        $target_id = $target_dxcc['id'];
        $target_name = $target_dxcc['name'];
        
        $competing_list = array();
        foreach ($competing_dxccs as $dxcc) {
            $competing_list[] = "{$dxcc['name']} (ID: {$dxcc['id']})";
        }
        $competing_str = implode(", ", $competing_list);
        
        $prompt = "你是一个专业的DXCC通联机器人，负责在多个有吸引力的DXCC实体之间进行优先级排序。

当前环境:
- 波段: {$current_band}
- 模式: {$current_mode}
- 可用时间窗口: {$available_time_slots}

目标实体:
- {$target_name} (ID: {$target_id})

竞争实体列表:
- {$competing_str}

请分析这些实体并进行优先级排序，回答:

1. 哪个实体应该获得最高优先级？
2. 如何分配有限的通联时间？
3. 有什么策略可以最大化DXCC收获？

请严格按照以下JSON格式回复:
{
  \"priority_ranking\": [\"ID1\", \"ID2\", \"ID3\"],
  \"time_allocation\": {
    \"{$target_id}\": \"percentage\"
  },
  \"strategy\": \"compete/focus_selective/focus_all/rotate\",
  \"reasoning\": \"优先级排序的理由\"
}

请提供你的优先级排序:";
        
        return $prompt;
    }
    
    /**
     * 分析频率活动
     */
    private function analyzeFrequencyActivity($signals, $band) {
        if (empty($signals)) {
            return "无其他活动";
        }
        
        $activity = array(
            'total_signals' => count($signals),
            'unique_dxcc' => array(),
            'strong_signals' => 0,
            'weak_signals' => 0
        );
        
        foreach ($signals as $signal) {
            if (isset($signal['dxcc'])) {
                $activity['unique_dxcc'][] = $signal['dxcc'];
            }
            if (isset($signal['snr']) && $signal['snr'] > -10) {
                $activity['strong_signals']++;
            } else {
                $activity['weak_signals']++;
            }
        }
        
        $activity['unique_dxcc'] = array_unique($activity['unique_dxcc']);
        
        return "总共检测到{$activity['total_signals']}个信号，{$this->getChineseNumber(count($activity['unique_dxcc']))}个不同DXCC实体，" .
               "{$activity['strong_signals']}个强信号，{$activity['weak_signals']}个弱信号";
    }
    
    /**
     * 摘要传播条件
     */
    private function summarizePropagationConditions($conditions, $band) {
        if (empty($conditions)) {
            return "未知传播条件";
        }
        
        // 简化的传播条件分析
        $summary = "";
        if (isset($conditions[$band])) {
            $band_conditions = $conditions[$band];
            $summary = $band . "波段: ";
            if (isset($band_conditions['sfi']) && $band_conditions['sfi'] > 100) {
                $summary .= "太阳通量高，传播良好";
            } else {
                $summary .= "传播条件一般";
            }
        } else {
            $summary = "传播条件未知";
        }
        
        return $summary;
    }
    
    /**
     * 摘要最近的CQ信号
     */
    private function summarizeRecentCQSignals($signals, $band) {
        if (empty($signals)) {
            return "无最近CQ信号";
        }
        
        $cq_count = 0;
        $unique_cq_dxcc = array();
        
        foreach ($signals as $signal) {
            if (isset($signal['message']) && stripos($signal['message'], 'cq') !== false) {
                $cq_count++;
                if (isset($signal['dxcc'])) {
                    $unique_cq_dxcc[] = $signal['dxcc'];
                }
            }
        }
        
        $unique_cq_dxcc = array_unique($unique_cq_dxcc);
        
        return "最近检测到{$this->getChineseNumber($cq_count)}个CQ信号，涉及{$this->getChineseNumber(count($unique_cq_dxcc))}个不同DXCC实体";
    }
    
    /**
     * 获取波段特性
     */
    private function getBandCharacteristics($band, $time) {
        $hour = (int)substr($time, 0, 2);
        
        switch ($band) {
            case '10m':
                if ($hour >= 8 && $hour <= 18) {
                    return "白天传播良好";
                } else {
                    return "夜间传播有限";
                }
            case '20m':
                if ($hour >= 6 && $hour <= 20) {
                    return "全天传播良好，白天稍差";
                } else {
                    return "夜间传播极佳";
                }
            case '40m':
                if ($hour >= 6 && $hour <= 18) {
                    return "白天传播受限";
                } else {
                    return "夜间传播最佳";
                }
            case '80m':
            case '160m':
                return "主要夜间传播";
            default:
                return "一般传播";
        }
    }
    
    /**
     * 将数字转为中文数字
     */
    private function getChineseNumber($num) {
        $nums = ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九', '十'];
        if ($num <= 10) {
            return $nums[$num];
        } else {
            return $num; // 对于大于10的数字，保持阿拉伯数字
        }
    }
}
?>