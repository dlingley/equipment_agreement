import re
import sys
from datetime import datetime

def extract_id_from_debug(line):
    # Extract timestamp if present
    timestamp_match = re.search(r'\[([\d-]+ [\d:]+)\]', line)
    timestamp = timestamp_match.group(1) if timestamp_match else None

    # Look for Purdue ID in API call lines
    api_match = re.search(r'Purdue ID: (\d+)', line)
    if api_match:
        return api_match.group(1), timestamp, line.strip()
    # Look for user ID in log entries
    log_match = re.search(r'user: (\d+)', line)
    if log_match:
        return log_match.group(1), timestamp, line.strip()
    return None, None, None

def normalize_id(id_str):
    # Remove any non-digit characters
    id_str = ''.join(c for c in id_str if c.isdigit())
    
    # Valid IDs should be 8-10 digits after removing leading zeros
    id_str = id_str.lstrip('0')
    if len(id_str) < 8 or len(id_str) > 10:
        return None
        
    # Remove trailing zeros and common suffixes (01, 02 etc)
    id_str = re.sub(r'(0+|0[12])$', '', id_str)

    # Ensure remaining ID is still valid length
    if len(id_str) < 8:
        return None
    
    return id_str

def main():
    debug_ids = {}  # Changed to dict to store timestamp and line info
    checkin_ids = set()
    
    # Process debug log
    with open('logs/debug.log', 'r') as f:
        for line in f:
            id_str, timestamp, log_line = extract_id_from_debug(line)
            if id_str:
                normalized = normalize_id(id_str)
                if normalized:
                    if normalized not in debug_ids:
                        debug_ids[normalized] = (timestamp, log_line)
    
    # Process checkin log
    with open('logs/checkin_log.csv', 'r') as f:
        for line in f:
            # Extract ID (first part of each line before the timestamp)
            id_str = line.split('2025-')[0].strip()
            if id_str:
                normalized = normalize_id(id_str)
                if normalized:
                    checkin_ids.add(normalized)
    
    # Find IDs in debug log but not in checkin log
    missing_ids = set(debug_ids.keys()) - checkin_ids
    
    # Generate report
    if missing_ids:
        print(f"\nFound {len(missing_ids)} IDs in debug.log that are missing from checkin_log.csv:")
        for id_str in sorted(missing_ids):
            timestamp, log_line = debug_ids[id_str]
            print(f"\nMissing ID: {id_str}")
            print(f"Timestamp: {timestamp}")
            print(f"Log entry: {log_line}")
    else:
        print("\nNo missing IDs found - all IDs in debug.log are present in checkin_log.csv")
    
    print(f"\nTotal unique IDs in debug.log: {len(debug_ids.keys())}")
    print(f"Total unique IDs in checkin_log.csv: {len(checkin_ids)}")

if __name__ == "__main__":
    main()
