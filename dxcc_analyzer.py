#!/usr/bin/env python3
"""
ULTRON DXCC Analyzer - Python Version

Analyzes ADIF log files to identify worked/unworked DXCC entities.
Provides whitelist recommendations and statistics.

Compatible with the PHP version functionality.
"""

import json
import re
import os
from datetime import datetime
from collections import defaultdict, Counter

class DXCCAnalyzer:
    def __init__(self):
        self.worked_entities = defaultdict(set)  # band -> set of dxcc_ids
        self.all_worked = set()  # all worked dxcc_ids across all bands
        self.dxcc_data = {}
        self.log_file = "wsjtx_log.adi"
        self.dxcc_file = "base.json"
        
    def load_dxcc_data(self):
        """Load DXCC entity data from base.json"""
        try:
            with open(self.dxcc_file, 'r', encoding='utf-8') as f:
                self.dxcc_data = json.load(f)
            print(f"✓ Loaded {len(self.dxcc_data)} DXCC entities from {self.dxcc_file}")
        except FileNotFoundError:
            print(f"⚠ Warning: {self.dxcc_file} not found. DXCC analysis will be limited.")
            self.dxcc_data = {}
        except json.JSONDecodeError as e:
            print(f"⚠ Error parsing {self.dxcc_file}: {e}")
            self.dxcc_data = {}
    
    def extract_callsign_info(self, callsign):
        """Extract country/entity info from callsign using base.json data"""
        if not self.dxcc_data:
            return None, None
            
        # Try exact match first
        if callsign in self.dxcc_data:
            entity = self.dxcc_data[callsign]
            return entity.get('dxcc_id'), entity.get('country', 'Unknown')
        
        # Try prefix matching
        for i in range(len(callsign), 0, -1):
            prefix = callsign[:i]
            if prefix in self.dxcc_data:
                entity = self.dxcc_data[prefix]
                return entity.get('dxcc_id'), entity.get('country', 'Unknown')
        
        return None, None
    
    def parse_adif_line(self, line):
        """Parse a single ADIF line and extract relevant data"""
        line = line.strip()
        if not line or line.startswith('<'):
            return None
            
        # Extract callsign and band
        callsign_match = re.search(r'<CALL:(\d+)>([^<]+)', line)
        band_match = re.search(r'<BAND:(\d+)>([^<]+)', line)
        
        if not callsign_match:
            return None
            
        callsign = callsign_match.group(2).strip()
        band = band_match.group(2).strip() if band_match else "Unknown"
        
        return {
            'callsign': callsign,
            'band': band
        }
    
    def analyze_log_file(self):
        """Analyze the ADIF log file and extract worked entities"""
        if not os.path.exists(self.log_file):
            print(f"⚠ Warning: {self.log_file} not found. Creating analysis without log data.")
            return
        
        try:
            with open(self.log_file, 'r', encoding='utf-8') as f:
                lines = f.readlines()
            
            print(f"📊 Analyzing {len(lines)} lines from {self.log_file}")
            
            for line_num, line in enumerate(lines, 1):
                parsed = self.parse_adif_line(line)
                if parsed:
                    callsign = parsed['callsign']
                    band = parsed['band']
                    
                    # Extract DXCC info
                    dxcc_id, country = self.extract_callsign_info(callsign)
                    
                    if dxcc_id:
                        self.worked_entities[band].add(dxcc_id)
                        self.all_worked.add(dxcc_id)
                        
        except Exception as e:
            print(f"⚠ Error analyzing log file: {e}")
    
    def get_dxcc_name(self, dxcc_id):
        """Get DXCC entity name from ID"""
        if not self.dxcc_data:
            return f"DXCC-{dxcc_id}"
            
        # Search through DXCC data to find entity name
        for prefix, info in self.dxcc_data.items():
            if info.get('dxcc_id') == dxcc_id:
                return info.get('country', f"DXCC-{dxcc_id}")
        
        return f"DXCC-{dxcc_id}"
    
    def generate_statistics(self):
        """Generate comprehensive DXCC statistics"""
        print("\n" + "="*60)
        print("📊 DXCC ANALYSIS REPORT")
        print("="*60)
        
        # Overall statistics
        print(f"\n🔍 OVERALL STATISTICS:")
        print(f"   Total worked DXCC entities: {len(self.all_worked)}")
        print(f"   Total available DXCC entities: {len(set(self.dxcc_data.get('dxcc_id', {}).keys())) if self.dxcc_data else 'Unknown'}")
        
        # Band-specific statistics
        print(f"\n📡 BAND-SPECIFIC STATISTICS:")
        for band in sorted(self.worked_entities.keys()):
            count = len(self.worked_entities[band])
            print(f"   {band}: {count} entities")
        
        # Top entities worked
        if self.dxcc_data:
            print(f"\n🏆 TOP DXCC ENTITIES WORKED:")
            entity_counts = Counter()
            for band, entities in self.worked_entities.items():
                for entity in entities:
                    entity_counts[entity] += 1
            
            for entity_id, count in entity_counts.most_common(10):
                name = self.get_dxcc_name(entity_id)
                print(f"   {name} (ID: {entity_id}): {count} bands")
    
    def generate_whitelist_recommendations(self):
        """Generate whitelist recommendations based on analysis"""
        print(f"\n🎯 WHITELIST RECOMMENDATIONS:")
        
        if not self.dxcc_data:
            print("   ⚠ Cannot generate recommendations without DXCC data")
            return
        
        # Get all available DXCC entities
        all_entities = set()
        for prefix, info in self.dxcc_data.items():
            if 'dxcc_id' in info:
                all_entities.add(info['dxcc_id'])
        
        # Find unworked entities
        unworked = all_entities - self.all_worked
        
        print(f"   Unworked DXCC entities: {len(unworked)}")
        
        if unworked:
            print(f"\n   🔍 TOP UNWORKED ENTITIES TO TARGET:")
            # Get entity names and sort
            unworked_with_names = []
            for entity_id in unworked:
                name = self.get_dxcc_name(entity_id)
                unworked_with_names.append((name, entity_id))
            
            unworked_with_names.sort()
            
            print("   PHP Format:")
            print("   $dxcc_whitelist = array(")
            for name, entity_id in unworked_with_names[:15]:  # Top 15
                print(f"       \"{entity_id}\" => \"{name}\",")
            print("   );")
            
            print("\n   Python Format:")
            print("   dxcc_whitelist = {")
            for name, entity_id in unworked_with_names[:15]:  # Top 15
                print(f"       \"{entity_id}\": \"{name}\",")
            print("   }")
    
    def export_statistics(self):
        """Export statistics to JSON file"""
        stats = {
            'analysis_date': datetime.now().isoformat(),
            'total_worked': len(self.all_worked),
            'band_stats': {band: len(entities) for band, entities in self.worked_entities.items()},
            'worked_entities': list(self.all_worked),
            'dxcc_data_loaded': bool(self.dxcc_data)
        }
        
        try:
            with open('worked_dxcc_cache.json', 'w', encoding='utf-8') as f:
                json.dump(stats, f, indent=2, ensure_ascii=False)
            print(f"\n✓ Statistics exported to worked_dxcc_cache.json")
        except Exception as e:
            print(f"⚠ Error exporting statistics: {e}")

def main():
    """Main function"""
    print("🚀 ULTRON DXCC Analyzer - Python Version")
    print("="*50)
    
    analyzer = DXCCAnalyzer()
    
    # Load DXCC data
    analyzer.load_dxcc_data()
    
    # Analyze log file
    analyzer.analyze_log_file()
    
    # Generate statistics
    analyzer.generate_statistics()
    
    # Generate whitelist recommendations
    analyzer.generate_whitelist_recommendations()
    
    # Export statistics
    analyzer.export_statistics()
    
    print(f"\n✓ Analysis complete!")
    print(f"📁 Check worked_dxcc_cache.json for detailed statistics")

if __name__ == "__main__":
    main()