#!/usr/bin/env python3
import re
import xml.etree.ElementTree as ET
import sys
from datetime import datetime, timedelta
import pytz

def parse_timestamp(log_line):
    """Extract timestamp from log line and convert to Eastern time."""
    match = re.match(r'\[(.*?)\]', log_line)
    if match:
        ts_str = match.group(1)
        try:
            # Parse timestamp and ensure it's in Eastern time
            ts = datetime.strptime(ts_str, '%Y-%m-%d %H:%M:%S')
            eastern = pytz.timezone('America/Indianapolis')
            ts = eastern.localize(ts)
            return ts
        except ValueError:
            return None  # Handle invalid timestamp format
    return None

def extract_purdue_id(log_line):
    """Extract Purdue ID from API call or PUT request log line."""
    match = re.search(r'Purdue ID: (\w+)', log_line)
    if not match:
        match = re.search(r'PUT Request URL: .*?/users/(\w+)\?', log_line)
    return match.group(1) if match else None

def extract_user_group(xml_text):
    """Extract user group from XML response."""
    try:
        xml_text = re.sub(r'<\?xml.*?\?>', '', xml_text)  # Remove XML declaration
        xml_text = re.sub(r'standalone="yes"', '', xml_text)  # Remove standalone attribute
        root = ET.fromstring(xml_text)
        user_group = root.find(".//user_group")
        return user_group.text if user_group is not None else None
    except ET.ParseError:
        return None

def get_visit_count(log_line):
    """Extract visit count from check-in log line."""
    match = re.search(r'Visit #(\d+)', log_line)
    return int(match.group(1)) if match else None

def process_debug_log(debug_log_path, output_path):
    """Process debug log and write recovered check-ins to output file."""
    recovered_entries = set()  # Track unique entries
    current_entry = {}
    timeout_seconds = 60  # Timeout for PUT request

    print(f"Processing debug log: {debug_log_path}")
    print(f"Output will be written to: {output_path}")

    try:
        with open(debug_log_path, 'r') as f:
            lines = f.readlines()
    except FileNotFoundError:
        print(f"Error: Debug log file not found: {debug_log_path}")
        return

    with open(output_path, 'a') as out_f:
        for i, line in enumerate(lines):
            timestamp = parse_timestamp(line)

            # Check for new API call
            if 'Starting API call for Purdue ID:' in line:
                new_purdue_id = extract_purdue_id(line)
                # Reset current_entry if it's a new Purdue ID or timeout
                if current_entry and (current_entry.get('purdue_id') != new_purdue_id or \
                                      (timestamp - current_entry.get('timestamp', timestamp)) > timedelta(seconds=timeout_seconds)):
                    current_entry = {}

                current_entry = {
                    'timestamp': timestamp,
                    'purdue_id': new_purdue_id,
                    'put_request': False
                }

            # Check for PUT request
            elif 'PUT Request URL:' in line and current_entry.get('purdue_id') == extract_purdue_id(line):
                current_entry['put_request'] = True

            # Look for XML response
            elif '<?xml' in line and current_entry.get('timestamp') and (timestamp - current_entry.get('timestamp')) <= timedelta(seconds=timeout_seconds):
                xml_text = line
                j = i + 1
                while j < len(lines) and '<?xml' not in lines[j]:
                    xml_text += lines[j]
                    j += 1
                current_entry['user_group'] = extract_user_group(xml_text)

            # Look for visit count and successful agreement
            elif 'Logged check-in for user:' in line and current_entry.get('timestamp') and (timestamp - current_entry.get('timestamp')) <= timedelta(seconds=timeout_seconds):
                visit_count = get_visit_count(line)
                log_purdue_id = extract_purdue_id(line)

                if log_purdue_id != current_entry.get('purdue_id'):
                    continue #skip if the logged in user is different

                # Only process if we have all required information and PUT request was successful
                if all(key in current_entry for key in ['timestamp', 'purdue_id', 'user_group']) and current_entry.get('put_request') and visit_count:
                    entry_key = f"{current_entry['purdue_id']},{current_entry['timestamp'].strftime('%Y-%m-%d %H:%M:%S')}"

                    if entry_key not in recovered_entries:
                        out_f.write(f"{current_entry['purdue_id']},{current_entry['timestamp'].strftime('%Y-%m-%d %H:%M:%S')},"
                                  f"{current_entry['user_group']},{visit_count}\n")
                        recovered_entries.add(entry_key)
                        print(f"Recovered entry for {current_entry['purdue_id']} at {current_entry['timestamp']}")

                current_entry = {}  # Clear entry after processing the check-in

def main():
    if len(sys.argv) != 3:
        print("Usage: python3 recovery_script.py <debug_log_path> <output_path>")
        sys.exit(1)
    
    debug_log_path = sys.argv[1]
    output_path = sys.argv[2]
    
    process_debug_log(debug_log_path, output_path)

if __name__ == "__main__":
    main()
