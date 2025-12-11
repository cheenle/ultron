<?php
/**
 * DXCC白名单管理器
 * 提供白名单的加载、更新、管理功能
 */

class DXCCWhitelistManager {
    private $config_dir;
    private $worked_cache_file;
    private $global_whitelist_file;
    private $band_whitelist_pattern;
    private $base_file;
    
    public function __construct($config_dir = '.') {
        $this->config_dir = $config_dir;
        $this->worked_cache_file = $config_dir . '/dxcc_worked_cache.json';
        $this->global_whitelist_file = $config_dir . '/dxcc_whitelist_global.json';
        $this->band_whitelist_pattern = $config_dir . '/dxcc_whitelist_{band}.json';
        $this->base_file = $config_dir . '/base.json';
    }
    
    /**
     * 加载白名单配置
     */
    public function loadWhitelist($band = null) {
        $whitelist = array();
        
        // 加载全球白名单
        if (file_exists($this->global_whitelist_file)) {
            $global = json_decode(file_get_contents($this->global_whitelist_file), true);
            if (is_array($global)) {
                foreach ($global as $id => $data) {
                    $whitelist[$id] = $data['name'];
                }
            }
        }
        
        // 如果指定了波段，加载波段特定的白名单
        if ($band !== null) {
            $band_file = str_replace('{band}', $band, $this->band_whitelist_pattern);
            if (file_exists($band_file)) {
                $band_list = json_decode(file_get_contents($band_file), true);
                if (is_array($band_list)) {
                    foreach ($band_list as $id => $data) {
                        $whitelist[$id] = $data['name'];
                    }
                }
            }
        }
        
        return $whitelist;
    }
    
    /**
     * 检查实体是否在白名单中（且未通联）
     */
    public function isInWhitelist($dxcc_id, $band = null) {
        // 首先检查是否已在通联缓存中
        if ($this->isWorked($dxcc_id)) {
            return false; // 已通联的实体不应在白名单中出现
        }
        
        $whitelist = $this->loadWhitelist($band);
        return isset($whitelist[$dxcc_id]);
    }
    
    /**
     * 检查实体是否已通联
     */
    public function isWorked($dxcc_id) {
        if (!file_exists($this->worked_cache_file)) {
            return false;
        }
        
        $worked = json_decode(file_get_contents($this->worked_cache_file), true);
        if (!is_array($worked)) {
            return false;
        }
        
        return isset($worked[$dxcc_id]);
    }
    
    /**
     * 从白名单中移除实体（自动更新）
     */
    public function removeFromWhitelist($dxcc_id, $reason = 'worked') {
        $updated = false;
        
        // 从全球白名单中移除
        if ($this->removeFromFile($this->global_whitelist_file, $dxcc_id)) {
            $updated = true;
        }
        
        // 从所有波段白名单中移除
        $bands = array('160m', '80m', '40m', '30m', '20m', '17m', '15m', '12m', '10m', '6m');
        foreach ($bands as $band) {
            $band_file = str_replace('{band}', $band, $this->band_whitelist_pattern);
            if ($this->removeFromFile($band_file, $dxcc_id)) {
                $updated = true;
            }
        }
        
        // 添加到已通联缓存
        if ($updated) {
            $this->addToWorkedCache($dxcc_id, $reason);
        }
        
        return $updated;
    }
    
    /**
     * 从指定文件中移除实体
     */
    private function removeFromFile($file, $dxcc_id) {
        if (!file_exists($file)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data) || !isset($data[$dxcc_id])) {
            return false;
        }
        
        $removed_data = $data[$dxcc_id];
        unset($data[$dxcc_id]);
        
        // 备份原文件
        $backup_file = $file . '.backup.' . date('YmdHis');
        copy($file, $backup_file);
        
        // 保存更新后的数据
        if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            // 记录日志
            $this->logUpdate("Removed DXCC $dxcc_id ({$removed_data['name']}) from " . basename($file));
            return true;
        }
        
        return false;
    }
    
    /**
     * 添加到已通联缓存
     */
    private function addToWorkedCache($dxcc_id, $reason = 'worked') {
        $worked = array();
        if (file_exists($this->worked_cache_file)) {
            $worked = json_decode(file_get_contents($this->worked_cache_file), true);
        }
        
        $worked[$dxcc_id] = array(
            'date' => date('Y-m-d H:i:s'),
            'reason' => $reason,
            'band' => null, // 可以在调用时传入
            'mode' => null  // 可以在调用时传入
        );
        
        file_put_contents($this->worked_cache_file, json_encode($worked, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * 处理QSO完成后的白名单更新
     */
    public function processQSOCompletion($callsign, $dxcc_id, $dxcc_name, $band = null, $mode = null) {
        // 检查是否已经在白名单中
        if ($this->isInWhitelist($dxcc_id, $band)) {
            echo "🎯 通联完成: $callsign ($dxcc_name) - 从白名单移除\n";
            
            // 从白名单中移除
            $this->removeFromWhitelist($dxcc_id, 'qso_completed');
            
            // 更新已通联缓存
            $this->addToWorkedCache($dxcc_id, "QSO: $callsign on $band $mode");
            
            return true;
        }
        
        return false;
    }
    
    /**
     * 获取白名单统计信息
     */
    public function getWhitelistStats() {
        $stats = array(
            'global' => 0,
            'bands' => array(),
            'worked' => 0,
            'files' => array()
        );
        
        // 全球白名单统计
        if (file_exists($this->global_whitelist_file)) {
            $global = json_decode(file_get_contents($this->global_whitelist_file), true);
            $stats['global'] = count($global);
            $stats['files'][] = $this->global_whitelist_file;
        }
        
        // 波段白名单统计
        $bands = array('160m', '80m', '40m', '30m', '20m', '17m', '15m', '12m', '10m', '6m');
        foreach ($bands as $band) {
            $band_file = str_replace('{band}', $band, $this->band_whitelist_pattern);
            if (file_exists($band_file)) {
                $band_list = json_decode(file_get_contents($band_file), true);
                $stats['bands'][$band] = count($band_list);
                $stats['files'][] = $band_file;
            }
        }
        
        // 已通联统计
        if (file_exists($this->worked_cache_file)) {
            $worked = json_decode(file_get_contents($this->worked_cache_file), true);
            $stats['worked'] = count($worked);
            $stats['files'][] = $this->worked_cache_file;
        }
        
        return $stats;
    }
    
    /**
     * 记录更新日志
     */
    private function logUpdate($message) {
        $log_file = 'whitelist_updates.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 验证白名单文件完整性
     */
    public function validateWhitelistFiles() {
        $issues = array();
        
        // 检查必需文件
        $required_files = array($this->global_whitelist_file);
        foreach ($required_files as $file) {
            if (!file_exists($file)) {
                $issues[] = "缺失必需文件: " . basename($file);
            } else {
                // 验证JSON格式
                $content = file_get_contents($file);
                $data = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $issues[] = "JSON格式错误: " . basename($file) . " - " . json_last_error_msg();
                }
            }
        }
        
        return $issues;
    }
}
?>