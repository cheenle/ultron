<?php
/*
 * Shared Data Handler for JTDX Web Interface
 * This file provides shared storage for real-time JTDX data
 * between robot_dxcc.php and the web interface
 */

class JTDXSharedData {
    private $dataFile;
    
    public function __construct($dataFile = 'jtdx_shared_data.json') {
        $this->dataFile = $dataFile;
        // 确保文件存在且格式正确
        if (!file_exists($this->dataFile)) {
            $this->saveData([
                'decodes' => [],
                'status' => [
                    'cq_active' => false,
                    'current_target' => '',
                    'decoded_count' => 0,
                    'transmitting' => false
                ],
                'config' => [
                    'decall' => '',
                    'software' => 'JTDX',
                    'mode' => 'FT8',
                    'band' => '20m'
                ]
            ]);
        }
    }
    
    public function addDecode($decode) {
        $data = $this->loadData();
        
        // 添加时间戳
        $decode['timestamp'] = time();
        
        // 将新的解码添加到数组开头
        array_unshift($data['decodes'], $decode);
        
        // 限制解码历史数量，避免文件过大
        $data['decodes'] = array_slice($data['decodes'], 0, 100);
        
        // 更新解码计数
        $data['status']['decoded_count'] = count($data['decodes']);
        
        $this->saveData($data);
    }
    
    public function updateStatus($status) {
        $data = $this->loadData();
        $data['status'] = array_merge($data['status'], $status);
        $this->saveData($data);
    }
    
    public function getConfig() {
        $data = $this->loadData();
        return $data['config'];
    }
    
    public function setConfig($config) {
        $data = $this->loadData();
        $data['config'] = array_merge($data['config'], $config);
        $this->saveData($data);
    }
    
    public function getLatestDecodes($limit = 50) {
        $data = $this->loadData();
        return array_slice($data['decodes'], 0, $limit);
    }
    
    public function getStatus() {
        $data = $this->loadData();
        return $data['status'];
    }
    
    private function loadData() {
        if (file_exists($this->dataFile)) {
            $content = file_get_contents($this->dataFile);
            if ($content) {
                $data = json_decode($content, true);
                if ($data) {
                    return $data;
                }
            }
        }
        
        // 如果文件不存在或解析失败，返回默认数据
        return [
            'decodes' => [],
            'status' => [
                'cq_active' => false,
                'current_target' => '',
                'decoded_count' => 0,
                'transmitting' => false
            ],
            'config' => [
                'decall' => '',
                'software' => 'JTDX',
                'mode' => 'FT8',
                'band' => '20m'
            ]
        ];
    }
    
    private function saveData($data) {
        file_put_contents($this->dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    public function clearDecodes() {
        $data = $this->loadData();
        $data['decodes'] = [];
        $this->saveData($data);
    }
}

// 创建全局实例以便在其他地方使用
if (!isset($jtdx_shared_data)) {
    $jtdx_shared_data = new JTDXSharedData();
}
?>