/*
 * JTDX Web Interface JavaScript
 * Implements the functionality for displaying JTDX status and integrating robot_dxcc features
 */

class JTDXWebInterface {
    constructor() {
        this.isListening = false;
        this.isCQActive = false;
        this.currentCQCall = '';
        this.decall = '';
        this.software = '';
        this.mode = '';
        this.band = '';
        this.modeElement = null;
        this.bandElement = null;
        this.decodes = [];
        this.workedDxcc = new Set();
        this.workedDxccBands = {};
        this.dxccWhitelist = new Set();
        this.bandWhitelist = {};
        
        this.initElements();
        this.bindEvents();
        this.loadDxccData();
        this.updateStats();
    }
    
    initElements() {
        // 按钮元素
        this.startBtn = document.getElementById('start-btn');
        this.stopBtn = document.getElementById('stop-btn');
        this.sendCqBtn = document.getElementById('send-cq-btn');
        this.stopCqBtn = document.getElementById('stop-cq-btn');
        this.refreshBtn = document.getElementById('refresh-btn');
        
        // 状态元素
        this.connectionStatus = document.getElementById('connection-status');
        this.softwareName = document.getElementById('software-name');
        this.deCall = document.getElementById('de-call');
        this.modeElement = document.getElementById('mode');
        this.bandElement = document.getElementById('band');
        this.cqStatus = document.getElementById('cq-status');
        
        // 解码表和日志
        this.decodesBody = document.getElementById('decodes-body');
        this.logOutput = document.getElementById('log-output');
        
        // 统计信息
        this.todayQso = document.getElementById('today-qso');
        this.currentCq = document.getElementById('current-cq');
        this.newDxcc = document.getElementById('new-dxcc');
        this.whitelistCount = document.getElementById('whitelist-count');
    }
    
    bindEvents() {
        this.startBtn.addEventListener('click', () => this.startListening());
        this.stopBtn.addEventListener('click', () => this.stopListening());
        this.sendCqBtn.addEventListener('click', () => this.sendCQ());
        this.stopCqBtn.addEventListener('click', () => this.stopCQ());
        this.refreshBtn.addEventListener('click', () => this.refreshData());
        
        // 模拟数据更新（在实际应用中，这将通过WebSocket或定期轮询实现）
        this.simulateDataUpdates();
    }
    
    async loadDxccData() {
        try {
            // 从base.json加载DXCC数据
            const response = await fetch('base.json');
            if (response.ok) {
                this.dxccData = await response.json();
                this.logMessage('DXCC数据加载成功');
            } else {
                this.logMessage('警告: 无法加载DXCC数据，使用模拟数据');
                this.generateMockDxccData();
            }
        } catch (error) {
            this.logMessage('错误: 加载DXCC数据失败，使用模拟数据');
            this.generateMockDxccData();
        }
    }
    
    generateMockDxccData() {
        // 生成模拟DXCC数据
        this.dxccData = [
            { id: 'K', name: 'United States', flag: '🇺🇸', licencia: 'K W N BG1SB' },
            { id: 'VE', name: 'Canada', flag: '🇨🇦', licencia: 'VE VA VO' },
            { id: 'JA', name: 'Japan', flag: '🇯🇵', licencia: 'JA' },
            { id: 'VK', name: 'Australia', flag: '🇦🇺', licencia: 'VK' },
            { id: 'G', name: 'England', flag: '🇬🇧', licencia: 'G M' },
            { id: 'DL', name: 'Germany', flag: '🇩🇪', licencia: 'DL' },
            { id: 'F', name: 'France', flag: '🇫🇷', licencia: 'F' },
            { id: 'PA', name: 'Netherlands', flag: '🇳🇱', licencia: 'PA' },
            { id: 'SP', name: 'Poland', flag: '🇵🇱', licencia: 'SP' },
            { id: 'UA', name: 'European Russia', flag: '🇷🇺', licencia: 'UA' },
            { id: 'PY', name: 'Brazil', flag: '🇧🇷', licencia: 'PY' },
            { id: 'LU', name: 'Argentina', flag: '🇦🇷', licencia: 'LU' }
        ];
    }
    
    locateDXCC(call) {
        if (!this.dxccData) return { id: 'unknown', flag: '❌', name: 'Unknown' };
        
        const z = call.length;
        const cleanCall = call.replace(/[\/\\]/g, (match) => match === '/' ? '\/' : '\\\\');
        
        for (let i = z; i >= 1; i--) {
            const licenseTruncated = cleanCall.substring(0, i);
            for (const result of this.dxccData) {
                const licenseData = result.licencia.trim();
                const parts = licenseData.split(' ');
                if (parts.length > 1) {
                    const prefixes = parts.slice(1);
                    for (const prefix of prefixes) {
                        const cleanPrefix = prefix.replace(/[\/\(].*$/, '');
                        if (cleanPrefix === licenseTruncated) {
                            return {
                                id: result.id,
                                flag: result.flag,
                                name: result.name
                            };
                        }
                    }
                }
            }
        }
        
        return { id: 'unknown', flag: '❌', name: 'Unknown' };
    }
    
    async startListening() {
        this.isListening = true;
        this.connectionStatus.textContent = '已连接';
        this.connectionStatus.style.color = '#64DD17';
        this.startBtn.disabled = true;
        this.stopBtn.disabled = false;
        this.sendCqBtn.disabled = false;
        
        this.logMessage('开始监听JTDX数据...');
        
        // 获取配置数据
        await this.getConfiguration();
        
        // 开始定期获取解码数据
        this.startDataPolling();
    }
    
    stopListening() {
        this.isListening = false;
        this.connectionStatus.textContent = '未连接';
        this.connectionStatus.style.color = '#f44336';
        this.startBtn.disabled = false;
        this.stopBtn.disabled = true;
        this.sendCqBtn.disabled = true;
        this.stopCQ();
        
        // 停止数据轮询
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
            this.pollingInterval = null;
        }
        
        this.logMessage('停止监听JTDX数据');
    }
    
    async getConfiguration() {
        try {
            // 尝试从配置文件获取信息
            const configResponse = await fetch('dxcc_config.php');
            if (configResponse.ok) {
                // 模拟解析配置信息
                this.decall = 'BG1SB'; // 模拟DE呼号
                this.software = 'JTDX';
                this.mode = 'FT8';
                this.band = '20m';
                
                this.softwareName.textContent = this.software;
                this.deCall.textContent = this.decall;
                this.modeElement.textContent = this.mode;
                this.bandElement.textContent = this.band;
                
                this.logMessage(`配置加载: ${this.software} - ${this.decall}`);
            }
        } catch (error) {
            // 使用默认值
            this.decall = 'BG1SB';
            this.software = 'JTDX';
            this.mode = 'FT8';
            this.band = '20m';
            
            this.softwareName.textContent = this.software;
                this.deCall.textContent = this.decall;
                this.modeElement.textContent = this.mode;
                this.bandElement.textContent = this.band;
        }
    }
    
    sendCQ() {
        if (!this.currentCQCall) {
            this.logMessage('错误: 没有选择要CQ的呼号');
            return;
        }
        
        this.isCQActive = true;
        this.cqStatus.textContent = '发送中';
        this.cqStatus.style.color = '#64DD17';
        this.sendCqBtn.disabled = true;
        this.stopCqBtn.disabled = false;
        
        this.currentCq.textContent = this.currentCQCall;
        this.logMessage(`开始对 ${this.currentCQCall} 发送CQ`);
        
        // 模拟发送CQ
        this.simulateSendCQ(this.currentCQCall);
    }
    
    async stopCQ() {
        this.isCQActive = false;
        this.cqStatus.textContent = '停止';
        this.cqStatus.style.color = '#f44336';
        this.sendCqBtn.disabled = false;
        this.stopCqBtn.disabled = true;
        
        this.currentCQCall = '';
        this.currentCq.textContent = '-';
        this.logMessage('停止发送CQ');
        
        // 调用API停止CQ
        try {
            const response = await fetch('./jtdx_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=stop_cq'
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.logMessage(`CQ停止成功: ${result.message}`);
            } else {
                this.logMessage(`CQ停止失败: ${result.error || response.status}`);
            }
        } catch (error) {
            this.logMessage(`CQ停止错误: ${error.message}`);
        }
    }
    
    async simulateSendCQ(call) {
        // 向服务端发送CQ请求
        try {
            const response = await fetch('./jtdx_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=send_cq&call=${call}`
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.logMessage(`CQ发送到 ${call} 成功: ${result.message}`);
            } else {
                this.logMessage(`CQ发送失败: ${result.error || response.status}`);
            }
        } catch (error) {
            this.logMessage(`CQ发送错误: ${error.message}`);
        }
    }
    
    refreshData() {
        this.logMessage('手动刷新数据...');
        this.updateStats();
    }
    
    updateStats() {
        // 更新统计信息
        this.todayQso.textContent = this.decodes.filter(d => d.message.includes('73') || d.message.includes('RR73')).length;
        this.newDxcc.textContent = this.countNewDxcc();
        this.whitelistCount.textContent = this.dxccWhitelist.size;
    }
    
    countNewDxcc() {
        // 计算新DXCC的数量
        return this.decodes.filter(d => d.priority_reason === 'NEW DXCC').length;
    }
    
    startDataPolling() {
        // 定期从API获取JTDX数据（与JTDX解码间隔保持一致，通常为15秒左右）
        this.pollingInterval = setInterval(async () => {
            if (this.isListening) {
                await this.fetchDecodes();
            }
        }, 15000); // 每15秒获取一次数据，与JTDX解码周期一致
        
        // 定期更新状态
        this.statusInterval = setInterval(async () => {
            if (this.isListening) {
                await this.fetchStatus();
            }
        }, 5000);
        
        // 定期更新统计信息
        this.statsInterval = setInterval(() => {
            if (this.isListening) {
                this.updateStats();
            }
        }, 15000);
    }
    
    async fetchDecodes() {
        try {
            const response = await fetch('./jtdx_api.php?action=get_decodes');
            const data = await response.json();
            
            if (data.decodes && Array.isArray(data.decodes) && data.decodes.length > 0) {
                // 获取当前时间
                const now = Math.floor(Date.now() / 1000);
                
                // 找到时间戳最新的解码作为基准
                const latestDecode = data.decodes.reduce((latest, current) => {
                    return (current.timestamp || 0) > (latest.timestamp || 0) ? current : latest;
                });
                
                // 找到与最新解码时间相近的解码（在5秒内）
                const latestTimestamp = latestDecode.timestamp || 0;
                const recentDecodes = data.decodes.filter(decode => {
                    return Math.abs(decode.timestamp - latestTimestamp) <= 5; // 在5秒内认为是同时到达的
                });
                
                // 清除现有数据并添加最新批次的解码
                this.decodesBody.innerHTML = '';
                this.decodes = [];
                
                // 显示最近的多个解码
                recentDecodes.forEach(decode => {
                    this.addDecodeRowFromAPI(decode);
                });
            }
        } catch (error) {
            console.error('获取解码数据失败:', error);
        }
    }
    
    async fetchStatus() {
        try {
            const response = await fetch('./jtdx_api.php?action=get_status');
            const status = await response.json();
            
            // 更新CQ状态显示
            this.cqStatus.textContent = status.cq_active ? '发送中' : '停止';
            this.cqStatus.style.color = status.cq_active ? '#64DD17' : '#f44336';
            
            // 更新当前目标
            this.currentCq.textContent = status.current_target || '-';
            
            // 根据状态更新按钮
            this.isCQActive = status.cq_active;
            if (status.cq_active) {
                this.sendCqBtn.disabled = true;
                this.stopCqBtn.disabled = false;
            } else {
                this.sendCqBtn.disabled = false;
                this.stopCqBtn.disabled = true;
            }
            
        } catch (error) {
            console.error('获取状态失败:', error);
        }
    }
    
    addDecodeRowFromAPI(decode) {
        // 直接使用来自robot_dxcc.php的优先级信息，而不是重新计算
        const priorityReason = decode.priority_reason || '';
        
        const decodeObj = {
            time: decode.time || '--:--:--',
            snr: decode.snr || '--',
            deltaF: decode.deltaF || '0',
            mode: decode.mode || 'FT8',
            status: decode.status || '  ', // 使用API提供的状态
            message: decode.message || `${decode.call} CQ`,
            dxcc: decode.dxcc || this.locateDXCC(decode.call).name,
            band: decode.band || this.getCurrentBand(),
            priority_reason: priorityReason,
            statusColor: this.getStatusColorForPriority(priorityReason),
            call: decode.call
        };
        
        this.decodes.push(decodeObj);
        
        const row = document.createElement('tr');
        row.className = 'fade-in';
        
        // 检查是否是新添加的行，如果是则添加高亮
        if (this.decodes.length > 0 && this.decodes[this.decodes.length - 1] === decodeObj) {
            row.classList.add('highlight');
            setTimeout(() => {
                row.classList.remove('highlight');
            }, 2000);
        }
        
        row.innerHTML = `
            <td>${decodeObj.time}</td>
            <td>${decodeObj.snr}</td>
            <td>${decodeObj.deltaF}</td>
            <td>${decodeObj.mode}</td>
            <td class="${decodeObj.statusColor}">${decodeObj.status}</td>
            <td>${decodeObj.message}</td>
            <td>${decodeObj.dxcc}</td>
            <td>${decodeObj.band}</td>
            <td>${decodeObj.priority_reason || '-'}</td>
            <td>
                <button class="action-btn" onclick="jtdxInterface.selectForCQ('${decodeObj.call}', this)">CQ</button>
            </td>
        `;
        
        // 始终将新行添加到顶部
        if (this.decodesBody.firstChild) {
            this.decodesBody.insertBefore(row, this.decodesBody.firstChild);
        } else {
            this.decodesBody.appendChild(row);
        }
        
        // 限制显示的行数
        if (this.decodesBody.children.length > 100) {
            this.decodesBody.removeChild(this.decodesBody.lastChild);
        }
    }
    
    isInBandWhitelist(dxccId, band) {
        return this.bandWhitelist[band]?.has(dxccId) || false;
    }
    
    getStatusColorForPriority(priority) {
        // 检查是否包含特定关键词
        if (priority.includes('NEW DXCC')) return 'status-9'; // 亮绿色
        if (priority.includes('GLOBAL WL')) return 'status-2'; // 绿色
        if (priority.includes('BAND WL')) return 'status-6'; // 青色
        if (priority.includes('NEW BAND')) return 'status-5'; // 紫色
        if (priority.includes('WL')) return 'status-3'; // 黄色
        return 'status-8'; // 灰色
    }
    
    isInBandWhitelist(dxccId, band) {
        return this.bandWhitelist[band]?.has(dxccId) || false;
    }
    
    getCurrentBand() {
        return this.band || '20m';
    }
    
    simulateDataUpdates() {
        // 模拟JTDX数据更新 - 仅作为API失败时的回退
        setInterval(() => {
            if (this.isListening && !this.pollingInterval) {
                // 随机生成一些解码数据
                if (Math.random() > 0.3) { // 70% 概率生成新数据
                    this.generateMockDecode();
                }
            }
        }, 15000); // 每15秒检查一次，与JTDX解码周期一致
        
        // 定期更新统计信息
        setInterval(() => {
            if (this.isListening) {
                this.updateStats();
            }
        }, 15000);
    }
    
    generateMockDecode() {
        const times = ['120028', '120030', '120032', '120035', '120038', '120041', '120044', '120047'];
        const snrs = ['-15', '-12', '-10', '-8', '-5', '-3', '0', '3', '5', '10'];
        const deltaFs = ['-120', '-85', '-42', '-18', '0', '25', '67', '128'];
        const modes = ['FT8', 'FT4', 'JT9', 'JT65', 'Q65', 'MSK144'];
        const statuses = ['  ', '--', '>>', '##', 'XX', 'FL', 'Lo'];
        const statusColors = ['status-8', 'status-1', 'status-2', 'status-8', 'status-4', 'status-8', 'status-3'];
        const dxccs = ['United States', 'Canada', 'Japan', 'Australia', 'England', 'Germany', 'France'];
        const bands = ['20m', '17m', '15m', '12m', '10m'];
        const priorityReasons = ['NEW DXCC', 'GLOBAL WL', 'BAND WL', 'NEW BAND', 'WL', ''];
        
        const calls = [
            'K1ABC', 'VE3XYZ', 'JA1ZZZ', 'VK2ABC', 'G3DEF', 
            'DL1ZZZ', 'F4ABC', 'PA5XYZ', 'SP6ABC', 'UA9XYZ',
            'PY1ABC', 'LU2XYZ', 'CE3ZZZ', 'JA2ABC'
        ];
        
        const time = times[Math.floor(Math.random() * times.length)];
        const snr = snrs[Math.floor(Math.random() * snrs.length)];
        const deltaF = deltaFs[Math.floor(Math.random() * deltaFs.length)];
        const mode = modes[Math.floor(Math.random() * modes.length)];
        const statusIndex = Math.floor(Math.random() * statuses.length);
        const status = statuses[statusIndex];
        const statusColor = statusColors[statusIndex];
        const call = calls[Math.floor(Math.random() * calls.length)];
        const dxcc = dxccs[Math.floor(Math.random() * dxccs.length)];
        const band = bands[Math.floor(Math.random() * bands.length)];
        const priorityReason = priorityReasons[Math.floor(Math.random() * priorityReasons.length)];
        
        // 根据状态生成消息
        let message = '';
        if (status === '>>') {
            message = `CQ ${call} ${Math.random() > 0.5 ? 'JN40' : 'IO91'}`;
        } else if (status === '--') {
            message = `${this.decall} ${call} ${Math.random() > 0.5 ? 'R-15' : '-12'}`;
        } else {
            message = `CQ ${call} ${Math.random() > 0.5 ? 'JN40' : 'IO91'}`;
        }
        
        const decode = {
            time: time,
            snr: snr,
            deltaF: deltaF,
            mode: mode,
            status: status,
            message: message,
            dxcc: dxcc,
            band: band,
            priority_reason: priorityReason,
            statusColor: statusColor,
            call: call
        };
        
        this.addDecodeRow(decode);
    }
    
    addDecodeRow(decode) {
        this.decodes.push(decode);
        
        const row = document.createElement('tr');
        row.className = 'fade-in';
        
        // 检查是否是新添加的行，如果是则添加高亮
        if (this.decodes.length > 0 && this.decodes[this.decodes.length - 1] === decode) {
            row.classList.add('highlight');
            setTimeout(() => {
                row.classList.remove('highlight');
            }, 2000);
        }
        
        row.innerHTML = `
            <td>${decode.time}</td>
            <td>${decode.snr}</td>
            <td>${decode.deltaF}</td>
            <td>${decode.mode}</td>
            <td class="${decode.statusColor}">${decode.status}</td>
            <td>${decode.message}</td>
            <td>${decode.dxcc}</td>
            <td>${decode.band}</td>
            <td>${decode.priority_reason || '-'}</td>
            <td>
                <button class="action-btn" onclick="jtdxInterface.selectForCQ('${decode.call}', this)" 
                    ${decode.status === '>>' ? '' : 'disabled'}>CQ</button>
            </td>
        `;
        
        // 始终将新行添加到顶部
        if (this.decodesBody.firstChild) {
            this.decodesBody.insertBefore(row, this.decodesBody.firstChild);
        } else {
            this.decodesBody.appendChild(row);
        }
        
        // 限制显示的行数
        if (this.decodesBody.children.length > 100) {
            this.decodesBody.removeChild(this.decodesBody.lastChild);
        }
    }
    
    selectForCQ(call, button) {
        // 高亮选中的行
        const row = button.closest('tr');
        const allRows = this.decodesBody.querySelectorAll('tr');
        allRows.forEach(r => r.classList.remove('selected'));
        row.classList.add('selected');
        
        // 设置为当前CQ呼号
        this.currentCQCall = call;
        
        // 更新按钮状态
        this.sendCqBtn.disabled = false;
        
        this.logMessage(`选择 ${call} 进行CQ操作`);
    }
    
    logMessage(message) {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = `[${timestamp}] ${message}\n`;
        
        // 限制日志长度
        if (this.logOutput.textContent.length > 5000) {
            this.logOutput.textContent = this.logOutput.textContent.substring(1000);
        }
        
        this.logOutput.textContent += logEntry;
        this.logOutput.scrollTop = this.logOutput.scrollHeight;
    }
}

// 添加行选择高亮样式
const style = document.createElement('style');
style.textContent = `
    tr.selected {
        background-color: rgba(76, 175, 80, 0.3) !important;
    }
`;
document.head.appendChild(style);

// 初始化JTDX Web Interface
const jtdxInterface = new JTDXWebInterface();

// 添加WebSocket连接以实时获取JTDX数据（如果可用）
function initWebSocket() {
    // 如果服务端支持WebSocket，则启用实时更新
    // 这里是示例实现，实际需要服务端支持
    if (window.WebSocket) {
        try {
            // 注意：在实际部署时，需要有后端WebSocket服务
            // const ws = new WebSocket('ws://localhost:8080');
            // ws.onmessage = function(event) {
            //     const data = JSON.parse(event.data);
            //     jtdxInterface.addDecodeRow(data);
            // };
        } catch (e) {
            console.log('WebSocket连接不可用，使用模拟数据');
        }
    }
}

// 页面加载完成后初始化WebSocket
document.addEventListener('DOMContentLoaded', initWebSocket);
