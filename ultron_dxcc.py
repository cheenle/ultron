#!/usr/bin/env python3
"""
ULTRON Enhanced - DXCC Targeting Version
Python Version with DXCC Whitelist Functionality

Created by: LU9DCE (Original PHP Version)
Enhanced by: Claude Code
Copyright: 2023 Eduardo Castillo
License: Creative Commons Attribution-NonCommercial-NoDerivatives 4.0 International
"""

import json
import os
from typing import Dict, List, Set, Optional
from dataclasses import dataclass, field
from pathlib import Path

from ultron import Ultron, Colors, TerminalUI

@dataclass
class DXCCConfig:
    """DXCC配置"""
    whitelist_only: bool = False  # 0 = 优先模式, 1 = 仅白名单
    dxcc_whitelist: Dict[str, str] = field(default_factory=dict)
    band_whitelist: Dict[str, Dict[str, str]] = field(default_factory=dict)
    
    def load_from_file(self, config_file: str = "dxcc_config.py"):
        """从配置文件加载"""
        if not os.path.exists(config_file):
            return
        
        try:
            # 尝试作为Python模块导入
            import importlib.util
            spec = importlib.util.spec_from_file_location("dxcc_config", config_file)
            config_module = importlib.util.module_from_spec(spec)
            spec.loader.exec_module(config_module)
            
            # 读取配置变量
            if hasattr(config_module, 'dxcc_whitelist_only'):
                self.whitelist_only = bool(config_module.dxcc_whitelist_only)
            if hasattr(config_module, 'dxcc_whitelist'):
                self.dxcc_whitelist = config_module.dxcc_whitelist
            if hasattr(config_module, 'band_whitelist'):
                self.band_whitelist = config_module.band_whitelist
                
        except Exception as e:
            print(f"{Colors.YELLOW}Warning: Could not load DXCC config: {e}{Colors.RESET}")

class DXCCAnalyzer:
    """DXCC分析器"""
    
    def __init__(self, dxcc_db, log_file: str = "wsjtx_log.adi"):
        self.dxcc_db = dxcc_db
        self.log_file = Path(log_file)
        self.worked_dxcc = {}  # {dxcc_id: name}
        self.worked_dxcc_by_band = {}  # {band: {dxcc_id: name}}
    
    def analyze_log(self) -> Dict[str, any]:
        """分析日志文件"""
        from ultron import ADIFProcessor
        
        if not self.log_file.exists():
            return {
                'worked_dxcc': {},
                'worked_dxcc_by_band': {},
                'unworked_dxcc': self.get_all_dxcc(),
                'total_worked': 0
            }
        
        try:
            content = self.log_file.read_text(encoding='utf-8')
            adif_processor = ADIFProcessor()
            qsos = adif_processor.parse_adif(content)
            
            for qso in qsos:
                if 'call' not in qso:
                    continue
                
                call = qso['call'].upper()
                band = qso.get('band', 'unknown')
                
                # 查找DXCC信息
                dxcc_info = self.dxcc_db.locate_call(call)
                if dxcc_info['id'] != 'unknown':
                    dxcc_id = dxcc_info['id']
                    dxcc_name = dxcc_info['name']
                    
                    # 添加到已通联列表
                    self.worked_dxcc[dxcc_id] = dxcc_name
                    
                    # 按波段记录
                    if band not in self.worked_dxcc_by_band:
                        self.worked_dxcc_by_band[band] = {}
                    self.worked_dxcc_by_band[band][dxcc_id] = dxcc_name
            
            # 计算未通联的DXCC
            all_dxcc = self.get_all_dxcc()
            unworked_dxcc = {k: v for k, v in all_dxcc.items() if k not in self.worked_dxcc}
            
            return {
                'worked_dxcc': self.worked_dxcc,
                'worked_dxcc_by_band': self.worked_dxcc_by_band,
                'unworked_dxcc': unworked_dxcc,
                'total_worked': len(self.worked_dxcc)
            }
            
        except Exception as e:
            print(f"{Colors.RED}Error analyzing log: {e}{Colors.RESET}")
            return {
                'worked_dxcc': {},
                'worked_dxcc_by_band': {},
                'unworked_dxcc': {},
                'total_worked': 0
            }
    
    def get_all_dxcc(self) -> Dict[str, str]:
        """获取所有DXCC实体"""
        all_dxcc = {}
        for entry in self.dxcc_db.database:
            all_dxcc[entry.get('id', 'unknown')] = entry.get('name', 'unknown')
        return all_dxcc
    
    def generate_recommendations(self, analysis_result: Dict[str, any]) -> Dict[str, List[str]]:
        """生成推荐白名单"""
        unworked = analysis_result['unworked_dxcc']
        
        recommendations = {
            'dxcc_whitelist': list(unworked.keys())[:10],  # 前10个未通联的DXCC
            'band_recommendations': {}
        }
        
        # 按波段推荐
        for band, worked_list in analysis_result['worked_dxcc_by_band'].items():
            unworked_in_band = {k: v for k, v in unworked.items() if k not in worked_list}
            if unworked_in_band:
                recommendations['band_recommendations'][band] = list(unworked_in_band.keys())[:5]
        
        return recommendations

class UltronDXCC(Ultron):
    """增强版ULTRON，支持DXCC白名单功能"""
    
    def __init__(self):
        super().__init__()
        self.dxcc_config = DXCCConfig()
        self.dxcc_analyzer = DXCCAnalyzer(self.dxcc_db)
        self.load_dxcc_configuration()
    
    def load_dxcc_configuration(self):
        """加载DXCC配置"""
        self.dxcc_config.load_from_file("dxcc_config.py")
        
        print(f"{Colors.CYAN} -----< ULTRON DXCC Enhanced: Loaded configuration{Colors.RESET}")
        print(f"{Colors.CYAN} -----< Whitelist Only Mode: {'ON' if self.dxcc_config.whitelist_only else 'OFF'}{Colors.RESET}")
        print(f"{Colors.CYAN} -----< DXCC Whitelist Count: {len(self.dxcc_config.dxcc_whitelist)}{Colors.RESET}")
        print(f"{Colors.CYAN} -----< Band Whitelist Count: {len(self.dxcc_config.band_whitelist)}{Colors.RESET}")
    
    def is_dxcc_in_whitelist(self, dxcc_id: str, band: str = None) -> bool:
        """检查DXCC是否在白名单中"""
        if not dxcc_id or dxcc_id == 'unknown':
            return False
        
        # 检查全局白名单
        if dxcc_id in self.dxcc_config.dxcc_whitelist:
            return True
        
        # 检查波段白名单
        if band and band in self.dxcc_config.band_whitelist:
            if dxcc_id in self.dxcc_config.band_whitelist[band]:
                return True
        
        return False
    
    def has_worked_dxcc_on_band(self, dxcc_id: str, band: str) -> bool:
        """检查是否在特定波段通联过该DXCC"""
        if not self.log_file.exists():
            return False
        
        try:
            from ultron import ADIFProcessor
            content = self.log_file.read_text(encoding='utf-8')
            adif_processor = ADIFProcessor()
            qsos = adif_processor.parse_adif(content)
            
            for qso in qsos:
                if 'call' not in qso:
                    continue
                
                call = qso['call'].upper()
                qso_band = qso.get('band', 'unknown')
                
                if qso_band == band:
                    dxcc_info = self.dxcc_db.locate_call(call)
                    if dxcc_info['id'] == dxcc_id:
                        return True
            
            return False
            
        except Exception as e:
            print(f"{Colors.YELLOW}Warning checking DXCC band status: {e}{Colors.RESET}")
            return False
    
    def handle_response_logic(self, parts: list, status: str, dxcc_info: dict) -> None:
        """重写响应逻辑，加入DXCC白名单判断"""
        call = parts[1]
        dxcc_id = dxcc_info.get('id', 'unknown')
        
        # 获取当前波段（简化处理，实际需要解析状态包）
        current_band = "20m"  # 需要根据实际频率确定
        
        # 检查是否在白名单中
        in_whitelist = self.is_dxcc_in_whitelist(dxcc_id, current_band)
        worked_on_band = self.has_worked_dxcc_on_band(dxcc_id, current_band)
        
        # 白名单优先模式
        if not self.dxcc_config.whitelist_only:
            # 优先响应白名单，但也会响应其他
            if in_whitelist and not worked_on_band and status == ">>":
                super().handle_response_logic(parts, status, dxcc_info)
            elif status == ">>" and call not in self.state.excluded_calls:
                super().handle_response_logic(parts, status, dxcc_info)
        else:
            # 仅响应白名单
            if in_whitelist and not worked_on_band and status == ">>":
                super().handle_response_logic(parts, status, dxcc_info)
    
    def analyze_and_recommend(self):
        """分析日志并生成推荐"""
        print(f"{Colors.CYAN}==== DXCC Analysis ===={Colors.RESET}")
        
        analysis = self.dxcc_analyzer.analyze_log()
        recommendations = self.dxcc_analyzer.generate_recommendations(analysis)
        
        # 打印分析结果
        print(f"\n{Colors.GREEN}已通联的DXCC实体: {analysis['total_worked']} 个{Colors.RESET}")
        print(f"{Colors.YELLOW}未通联的DXCC实体: {len(analysis['unworked_dxcc'])} 个{Colors.RESET}")
        
        # 打印推荐白名单
        print(f"\n{Colors.CYAN}推荐的DXCC白名单:{Colors.RESET}")
        for dxcc_id in recommendations['dxcc_whitelist']:
            name = analysis['unworked_dxcc'].get(dxcc_id, 'Unknown')
            print(f"  {dxcc_id}: {name}")
        
        # 打印波段推荐
        if recommendations['band_recommendations']:
            print(f"\n{Colors.CYAN}按波段的推荐:{Colors.RESET}")
            for band, dxcc_list in recommendations['band_recommendations'].items():
                print(f"\n{band}波段:")
                for dxcc_id in dxcc_list:
                    name = analysis['unworked_dxcc'].get(dxcc_id, 'Unknown')
                    print(f"  {dxcc_id}: {name}")

def main():
    """主函数"""
    import sys
    
    # 检查命令行参数
    if len(sys.argv) > 1 and sys.argv[1] == "analyze":
        # 分析模式
        print(f"{Colors.CYAN}==== DXCC Analysis Mode ===={Colors.RESET}")
        dxcc_db = UltronDXCC()
        dxcc_db.analyze_and_recommend()
    else:
        # 正常运行模式
        print(f"{Colors.CYAN}==== ULTRON DXCC Enhanced Mode ===={Colors.RESET}")
        ultron = UltronDXCC()
        ultron.run()

if __name__ == "__main__":
    main()