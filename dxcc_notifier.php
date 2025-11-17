<?php
// DXCC通知系统
// 检测新通联的DXCC实体并发送微信通知

require_once 'wechat_config.php';

class DXCCNotifier {
    private $worked_dxcc_file = 'worked_dxcc_cache.json';
    private $activity_cache_file = 'dxcc_activity_cache.json';
    private $wechat_config;
    private $base;
    private $notification_cooldown = 1800; // 30分钟内不重复通知同一DXCC
    
    public function __construct($wechat_config) {
        $this->wechat_config = $wechat_config;
        $this->load_base_data();
    }
    
    // 加载呼号数据库
    private function load_base_data() {
        $resultados_json = file_get_contents('base.json');
        $this->base = json_decode($resultados_json, true);
    }
    
    // 获取已通联的DXCC缓存
    private function get_worked_dxcc_cache() {
        if (file_exists($this->worked_dxcc_file)) {
            $cache = file_get_contents($this->worked_dxcc_file);
            return json_decode($cache, true) ?: array();
        }
        return array();
    }
    
    // 保存已通联的DXCC缓存
    private function save_worked_dxcc_cache($worked_dxcc) {
        file_put_contents($this->worked_dxcc_file, json_encode($worked_dxcc, JSON_PRETTY_PRINT));
    }
    
    // 获取活动缓存
    private function get_activity_cache() {
        if (file_exists($this->activity_cache_file)) {
            $cache = file_get_contents($this->activity_cache_file);
            return json_decode($cache, true) ?: array();
        }
        return array();
    }
    
    // 保存活动缓存
    private function save_activity_cache($activity_cache) {
        file_put_contents($this->activity_cache_file, json_encode($activity_cache, JSON_PRETTY_PRINT));
    }
    
    // 检查是否应该发送活动通知（防重复）
    private function should_send_activity_notification($dxcc_id) {
        $activity_cache = $this->get_activity_cache();
        $current_time = time();
        
        if (isset($activity_cache[$dxcc_id])) {
            $last_notification = $activity_cache[$dxcc_id]['last_notification'];
            // 如果上次通知在30分钟内，不重复通知
            if (($current_time - $last_notification) < $this->notification_cooldown) {
                return false;
            }
        }
        
        return true;
    }
    
    // 记录活动通知
    private function record_activity_notification($dxcc_id, $call, $band, $mode) {
        $activity_cache = $this->get_activity_cache();
        $activity_cache[$dxcc_id] = array(
            'last_notification' => time(),
            'last_call' => $call,
            'last_band' => $band,
            'last_mode' => $mode,
            'notification_count' => isset($activity_cache[$dxcc_id]) ? $activity_cache[$dxcc_id]['notification_count'] + 1 : 1
        );
        $this->save_activity_cache($activity_cache);
    }
    
    // 检测新通联的DXCC
    public function check_new_dxcc($call, $band, $mode, $time) {
        // 根据呼号查找DXCC信息
        $dxcc_info = $this->locate_dxcc($call);
        
        if (!$dxcc_info || $dxcc_info['id'] == 'unknown') {
            return false;
        }
        
        $worked_cache = $this->get_worked_dxcc_cache();
        $dxcc_id = $dxcc_info['id'];
        
        // 检查是否是新的DXCC实体
        if (!isset($worked_cache[$dxcc_id])) {
            // 新的DXCC实体！
            $worked_cache[$dxcc_id] = array(
                'name' => $dxcc_info['name'],
                'first_worked' => $time,
                'band' => $band,
                'call' => $call
            );
            
            $this->save_worked_dxcc_cache($worked_cache);
            
            // 发送微信通知
            $this->send_new_dxcc_notification($call, $dxcc_info, $band, $mode, $time);
            
            echo "🎉 发现新的DXCC实体: {$dxcc_info['name']} ({$dxcc_id})\n";
            return true;
        }
        
        return false;
    }
    
    // 检测DXCC实时活动 - 在解码时发现新DXCC立即通知
    public function check_dxcc_activity($call, $band, $mode, $snr, $time) {
        // 根据呼号查找DXCC信息
        $dxcc_info = $this->locate_dxcc($call);
        
        if (!$dxcc_info || $dxcc_info['id'] == 'unknown') {
            return false;
        }
        
        $dxcc_id = $dxcc_info['id'];
        $worked_cache = $this->get_worked_dxcc_cache();
        
        // 检查是否是未通联过的DXCC实体
        if (!isset($worked_cache[$dxcc_id])) {
            // 检查是否应该发送活动通知（避免频繁通知）
            if ($this->should_send_activity_notification($dxcc_id)) {
                // 发送实时活动通知
                $this->send_dxcc_activity_notification($call, $dxcc_info, $band, $mode, $snr, $time);
                
                // 记录这次通知
                $this->record_activity_notification($dxcc_id, $call, $band, $mode);
                
                echo "🎯 发现未通联DXCC实体活动: {$dxcc_info['name']} ({$call})\n";
                return true;
            }
        }
        
        return false;
    }
    
    // 发送DXCC实时活动通知
    private function send_dxcc_activity_notification($call, $dxcc_info, $band, $mode, $snr, $time) {
        if (!$this->wechat_config['enabled']) {
            return false;
        }
        
        $message = "🔍 发现未通联DXCC实体活动！\n\n";
        $message .= "⚠️ 重要提醒：这是一个您还未通联的DXCC实体！\n\n";
        $message .= "呼号: {$call}\n";
        $message .= "DXCC: {$dxcc_info['name']} ({$dxcc_info['id']})\n";
        $message .= "波段: {$band}\n";
        $message .= "模式: {$mode}\n";
        $message .= "信号: {$snr} dB\n";
        $message .= "时间: {$time}\n";
        
        if (isset($dxcc_info['flag'])) {
            $message .= "国旗: {$dxcc_info['flag']}\n";
        }
        
        // 获取统计信息
        $worked_cache = $this->get_worked_dxcc_cache();
        $total_worked = count($worked_cache);
        $remaining = 337 - $total_worked;
        
        $message .= "\n📊 当前统计:\n";
        $message .= "已通联DXCC: {$total_worked} 个\n";
        $message .= "剩余目标: {$remaining} 个\n";
        $message .= "完成度: " . round(($total_worked / 337) * 100, 1) . "%\n\n";
        
        $message .= "💡 建议：立即尝试呼叫这个稀有DXCC！\n";
        $message .= "🎯 策略：可以适当提高发射功率，多次尝试呼叫\n";
        
        return send_wechat_message($message, $this->wechat_config);
    }
    
    // 发送新DXCC通知
    private function send_new_dxcc_notification($call, $dxcc_info, $band, $mode, $time) {
        if (!$this->wechat_config['enabled']) {
            return false;
        }
        
        $message = format_dxcc_notification($call, $dxcc_info, $band, $mode, $time);
        
        // 添加更多统计信息
        $worked_cache = $this->get_worked_dxcc_cache();
        $total_worked = count($worked_cache);
        
        $message .= "\n📊 统计信息:\n";
        $message .= "累计通联DXCC: {$total_worked} 个\n";
        $message .= "剩余目标: " . (337 - $total_worked) . " 个\n";
        
        return send_wechat_message($message, $this->wechat_config);
    }
    
    // 根据呼号查找DXCC（复用现有逻辑）
    private function locate_dxcc($licrx) {
        $z = strlen($licrx);
        $licrx = str_replace(['\\', '/'], ['\\\\', '\\/'], $licrx);
        for ($i = $z; $i >= 1; $i--) {
            $licencia_recortada = substr($licrx, 0, $i);
            foreach ($this->base as $resultado) {
                $expresion_regular = '/\b ' . $licencia_recortada . '\b/';
                if (preg_match($expresion_regular, $resultado['licencia'])) {
                    return array(
                        'id' => $resultado['id'],
                        'flag' => $resultado['flag'],
                        'name' => $resultado['name']
                    );
                }
            }
        }
        return array(
            'id' => 'unknown',
            'flag' => 'unknown',
            'name' => 'unknown'
        );
    }
    
    // 同步当前日志文件到缓存
    public function sync_worked_dxcc_from_log() {
        echo "正在同步日志文件到DXCC缓存...\n";
        
        $log_file = 'wsjtx_log.adi';
        if (!file_exists($log_file)) {
            echo "日志文件不存在: $log_file\n";
            return false;
        }
        
        $worked_dxcc = array();
        $contents = file_get_contents($log_file);
        $qsos = explode('<eor>', $contents);
        
        foreach ($qsos as $qso) {
            if (strpos($qso, '<call:') !== false) {
                // 解析QSO记录
                preg_match('/<call:([0-9]+)>(\w+)/', $qso, $call_match);
                preg_match('/<band:([0-9]+)>(\w+)/', $qso, $band_match);
                preg_match('/<mode:([0-9]+)>(\w+)/', $qso, $mode_match);
                preg_match('/<qso_date:([0-9]+)>(\d+)/', $qso, $date_match);
                preg_match('/<time_on:([0-9]+)>(\d+)/', $qso, $time_match);
                
                if (isset($call_match[2])) {
                    $call = strtoupper($call_match[2]);
                    $dxcc_info = $this->locate_dxcc($call);
                    
                    if ($dxcc_info && $dxcc_info['id'] != 'unknown') {
                        $dxcc_id = $dxcc_info['id'];
                        
                        // 记录第一次通联的信息
                        if (!isset($worked_dxcc[$dxcc_id])) {
                            $worked_dxcc[$dxcc_id] = array(
                                'name' => $dxcc_info['name'],
                                'first_worked' => ($date_match[2] ?? '') . ' ' . ($time_match[2] ?? ''),
                                'band' => $band_match[2] ?? 'unknown',
                                'call' => $call
                            );
                        }
                    }
                }
            }
        }
        
        $this->save_worked_dxcc_cache($worked_dxcc);
        echo "同步完成！共找到 " . count($worked_dxcc) . " 个已通联的DXCC实体\n";
        return true;
    }
    
    // 获取统计信息
    public function get_stats() {
        $worked_cache = $this->get_worked_dxcc_cache();
        $total_worked = count($worked_cache);
        $remaining = 337 - $total_worked; // 基于当前白名单数量
        
        return array(
            'total_worked' => $total_worked,
            'remaining' => $remaining,
            'percentage' => round(($total_worked / 337) * 100, 2)
        );
    }
}

// 独立测试函数
function test_dxcc_notifier() {
    global $wechat_config;
    
    echo "=== 测试DXCC通知系统 ===\n";
    
    $notifier = new DXCCNotifier($wechat_config);
    
    // 同步现有日志
    $notifier->sync_worked_dxcc_from_log();
    
    // 显示统计
    $stats = $notifier->get_stats();
    echo "统计信息:\n";
    echo "已通联: {$stats['total_worked']} 个\n";
    echo "剩余: {$stats['remaining']} 个\n";
    echo "完成度: {$stats['percentage']}%\n";
    
    // 测试新DXCC检测（使用一个模拟的稀有DXCC）
    echo "\n测试新DXCC检测...\n";
    $result = $notifier->check_new_dxcc('3XY3D', '20m', 'FT8', date('Y-m-d H:i:s'));
    
    if ($result) {
        echo "✅ 新DXCC检测和通知功能正常\n";
    } else {
        echo "❌ 未检测到新DXCC（可能已存在或呼号无效）\n";
    }
}

// 如果直接运行此文件，执行测试
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    require_once 'wechat_config.php';
    test_dxcc_notifier();
}

?>