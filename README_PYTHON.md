# ULTRON Python Version 🐍

**自动化的JTDX/WSJT-X/MSHV控制工具**

这是ULTRON的Python重构版本，完全兼容原有的PHP版本功能，同时提供更好的跨平台支持和现代化的代码架构。

## 🚀 主要特性

- ✅ **跨平台支持**: Windows, Linux, macOS
- ✅ **完整UDP协议**: 兼容WSJT-X/JTDX/MSHV
- ✅ **智能QSO管理**: 自动CQ呼叫和响应
- ✅ **DXCC白名单**: 精确定位未通联实体
- ✅ **ADIF日志**: 独立日志文件管理
- ✅ **实时分析**: DXCC通联情况统计
- ✅ **彩色终端**: 美观的界面输出
- ✅ **模块化设计**: 易于扩展和维护

## 📋 系统要求

- Python 3.7或更高版本
- 网络权限（UDP端口2237）
- 终端支持ANSI颜色（推荐）

## 🔧 安装和配置

### 1. 安装Python
确保系统已安装Python 3.7+：
```bash
python --version
```

### 2. 克隆或下载代码
```bash
git clone [repository-url]
cd ultron-python
```

### 3. 配置文件
- `dxcc_config.py` - DXCC白名单配置
- `base.json` - DXCC数据库（可选，但推荐）

## 🎯 快速开始

### 交互式启动（推荐）
**Windows:**
```cmd
run_ultron.bat
```

**Linux/macOS:**
```bash
chmod +x run_ultron.sh
./run_ultron.sh
```

**跨平台Python脚本:**
```bash
python run_ultron.py
```

### 命令行参数
```bash
# 标准模式
python run_ultron.py standard

# DXCC增强模式
python run_ultron.py dxcc

# DXCC分析模式
python run_ultron.py analyze
```

## 📁 文件结构

```
ultron-python/
├── ultron.py              # 标准ULTRON主程序
├── ultron_dxcc.py         # 增强版ULTRON (DXCC功能)
├── dxcc_config.py         # DXCC白名单配置
├── run_ultron.py          # 跨平台启动脚本
├── run_ultron.sh          # Unix/Linux启动脚本
├── run_ultron.bat         # Windows启动脚本
├── base.json              # DXCC数据库
├── wsjtx_log.adi          # ADIF日志文件（自动生成）
└── README_PYTHON.md       # 本文档
```

## ⚙️ 配置说明

### DXCC白名单配置 (dxcc_config.py)

```python
# 白名单模式开关
# False = 优先白名单模式（推荐）
# True = 仅响应白名单模式
dxcc_whitelist_only = False

# 全局DXCC白名单
dxcc_whitelist = {
    "1": "USA",
    "110": "SPAIN", 
    "284": "BULGARIA",
    # ... 更多DXCC实体
}

# 按波段的白名单
band_whitelist = {
    "20m": {
        "1": "USA",
        "110": "SPAIN",
        # ...
    }
}
```

## 🔍 使用说明

### 基本操作
1. 确保JTDX/WSJT-X正在运行并配置UDP转发
2. 启动ULTRON，选择相应模式
3. 观察终端输出，系统会自动处理QSO

### 终端显示说明
```
HHMMSS  SNR  DF   MODE   ST MESSAGE               - DXCC_ENTITY
120345  -15   0   FT8    >> CQ K1ABC FN42        - UNITED STATES
```

状态代码：
- `>>` - 新目标，准备响应
- `--` - 已通联过
- `XX` - 在排除列表中
- `Lo` - 信号太弱

### DXCC分析功能
运行分析模式可查看：
- 已通联的DXCC实体统计
- 按波段的通联情况
- 推荐的DXCC白名单

## 🔧 高级配置

### 网络配置
在`ultron.py`中修改：
```python
UDP_PORT = 2237          # 监听端口
UDP_FORWARD_PORT = 2277  # 转发端口
TIMEOUT_SECONDS = 90     # 超时时间
SIGNAL_THRESHOLD = -20   # 信号阈值
```

### 模式支持
支持的模式：
- FT8
- FT4  
- JT65
- JT9
- FST4
- Q65
- MSK144

## 🐛 故障排除

### 常见问题

**1. Python版本错误**
```
错误: 需要Python 3.7或更高版本
```
解决方案：升级Python到3.7+

**2. 权限错误**
```
PermissionError: [Errno 13] Permission denied
```
解决方案：确保有网络权限，或尝试使用sudo（Linux）

**3. 文件找不到**
```
错误: 找不到ultron.py文件
```
解决方案：确认在正确的目录下运行

**4. DXCC数据库缺失**
```
警告: 找不到base.json文件
```
解决方案：DXCC功能受限，但基本功能正常

### 调试模式
在代码中添加调试输出：
```python
import logging
logging.basicConfig(level=logging.DEBUG)
```

## 📊 性能优化

### 内存使用
- 使用生成器处理大日志文件
- 缓存DXCC查询结果
- 定期清理状态变量

### 网络优化
- UDP数据包大小优化（512字节）
- socket超时设置
- 错误重试机制

## 🔐 安全考虑

- 仅监听本地网络接口
- 不存储敏感信息
- 日志文件权限控制
- 输入验证和清理

## 🤝 贡献指南

欢迎提交Issue和Pull Request：

1. Fork项目
2. 创建特性分支
3. 提交更改
4. 推送到分支
5. 创建Pull Request

## 📄 许可证

Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International

## 🙏 致谢

- **LU9DCE (Eduardo Castillo)** - 原始PHP版本作者
- **WSJT-X团队** - 优秀的数字模式软件
- **业余无线电社区** - 持续的支持和反馈

## 📞 联系方式

- 原始作者: castilloeduardo@outlook.com.ar
- 项目维护: [维护者联系信息]

---

**享受自动化通联的乐趣！73!**