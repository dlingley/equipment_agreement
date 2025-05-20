#!/usr/bin/env python3
import re
import json
import sys
import time
import xml.etree.ElementTree as ET
from datetime import datetime
import requests
import argparse

def parse_php_config(config_path):
    """Parse PHP config file for API key and settings."""
    try:
        with open(config_path, 'r') as f:
            config_content = f.read()
            
        # Extract API key using regex
        api_key_match = re.search(r"'ALMA_API_KEY'\s*=>\s*'([^']+)'", config_content)
        if not api_key_match:
            raise ValueError("Could not find ALMA_API_KEY in config")
        
        api_key = api_key_match.group(1)
        
        # Extract base URL
        base_url_match = re.search(r"'BASE_URL'\s*=>\s*'([^']+)'", config_content)
        if not base_url_match:
            raise ValueError("Could not find BASE_URL in config")
        
        base_url = base_url_match.group(1)
        
        # Extract log paths
        checkin_log_match = re.search(r"'CHECKIN'\s*=>\s*'([^']+)'", config_content)
        if not checkin_log_match:
            raise ValueError("Could not find CHECKIN log path in config")
        
        checkin_log = checkin_log_match.group(1)
        
        return {
            'api_key': api_key,
            'base_url': base_url,
            'checkin_log': checkin_log
        }
    except Exception as e:
        print(f"Error parsing config file: {str(e)}")
        sys.exit(1)

def get_unique_users(log_path):
    """Extract unique user IDs from the check-in log."""
    unique_users = set()
    try:
        with open(log_path, 'r') as f:
            for line in f:
                parts = line.strip().split(',')
                if len(parts) >= 1:
                    user_id = parts[0]
                    if user_id != "UNKNOWN" and not user_id.startswith("ERROR"):
                        unique_users.add(user_id)
    except FileNotFoundError:
        print(f"Error: Check-in log file not found at {log_path}")
        sys.exit(1)
    except Exception as e:
        print(f"Error reading check-in log: {str(e)}")
        sys.exit(1)
    
    return unique_users

def check_user_agreement(user_xml):
    """Check if user has the agreement note and return its segment type if found."""
    try:
        root = ET.fromstring(user_xml)
        notes = root.findall(".//user_note")
        for note in notes:
            note_text = note.find("note_text")
            if note_text is not None and "Agreed to Knowledge Lab User Agreement" in note_text.text:
                return True, note.get("segment_type")
    except ET.ParseError as e:
        print(f"Error parsing user XML: {str(e)}")
    return False, None

def fix_note_segment(user_xml):
    """Update agreement note's segment type to Internal."""
    try:
        root = ET.fromstring(user_xml)
        
        # Remove roles section to prevent conflicts
        roles = root.find(".//user_roles")
        if roles is not None:
            root.remove(roles)
        
        # Find and update the agreement note
        notes = root.findall(".//user_note")
        updated = False
        for note in notes:
            note_text = note.find("note_text")
            if note_text is not None and "Agreed to Knowledge Lab User Agreement" in note_text.text:
                note.set("segment_type", "Internal")
                updated = True
                break
        
        if not updated:
            return None
            
        return ET.tostring(root, encoding='unicode')
    except Exception as e:
        print(f"Error modifying user XML: {str(e)}")
        return None

def process_users(config, users, dry_run=False):
    """Process users and fix agreement note segments where needed."""
    session = requests.Session()
    results = {
        'processed': 0,
        'needs_update': 0,
        'updated': 0,
        'no_note': 0,
        'already_correct': 0,
        'failed': 0
    }
    
    for user_id in users:
        results['processed'] += 1
        print(f"\nProcessing user {user_id} ({results['processed']}/{len(users)})")
        
        try:
            # GET user data
            get_url = f"{config['base_url']}{user_id}?view=full&expand=none&apikey={config['api_key']}"
            response = session.get(get_url)
            
            if response.status_code != 200:
                print(f"Error getting user data: HTTP {response.status_code}")
                results['failed'] += 1
                continue
            
            # Check agreement note and segment type
            has_note, segment_type = check_user_agreement(response.text)
            
            if not has_note:
                print(f"User {user_id} does not have agreement note")
                results['no_note'] += 1
                continue
                
            if segment_type == "Internal":
                print(f"User {user_id} agreement note already in Internal segment")
                results['already_correct'] += 1
                continue
                
            print(f"User {user_id} has agreement note in {segment_type} segment")
            results['needs_update'] += 1
            
            # In dry run mode, just report what would be done
            if dry_run:
                print(f"Would update agreement note segment to Internal for user {user_id}")
                continue
            
            # Fix note segment
            modified_xml = fix_note_segment(response.text)
            if not modified_xml:
                print(f"Failed to modify XML for user {user_id}")
                results['failed'] += 1
                continue
            
            # PUT updated user data
            put_url = f"{config['base_url']}{user_id}?generate_password=false&send_pin_number_letter=false&recalculate_roles=false&apikey={config['api_key']}"
            put_response = session.put(put_url, data=modified_xml, headers={'Content-Type': 'application/xml'})
            
            if put_response.status_code == 200:
                print(f"Successfully updated note segment for user {user_id}")
                results['updated'] += 1
            else:
                print(f"Failed to update user {user_id}: HTTP {put_response.status_code}")
                results['failed'] += 1
            
            # Rate limiting
            time.sleep(0.5)  # 500ms delay between requests
            
        except Exception as e:
            print(f"Error processing user {user_id}: {str(e)}")
            results['failed'] += 1
    
    return results

def main():
    parser = argparse.ArgumentParser(description='Validate and fix agreement note segments in Alma')
    parser.add_argument('--dry-run', action='store_true', help='Preview changes without making them')
    args = parser.parse_args()
    
    print("Starting agreement note segment validation script...")
    if args.dry_run:
        print("DRY RUN MODE - No changes will be made")
    
    # Load configuration
    print("\nLoading configuration...")
    config = parse_php_config('config.php')
    
    # Get unique users from log
    print("\nReading check-in log...")
    users = get_unique_users(config['checkin_log'])
    print(f"Found {len(users)} unique users")
    
    # Process users
    print("\nProcessing users...")
    results = process_users(config, users, args.dry_run)
    
    # Print summary
    print("\nSummary:")
    print(f"Total users processed: {results['processed']}")
    print(f"Users with note in wrong segment: {results['needs_update']}")
    print(f"Users successfully updated: {results['updated']}")
    print(f"Users with note already in Internal segment: {results['already_correct']}")
    print(f"Users without agreement note: {results['no_note']}")
    print(f"Users with errors: {results['failed']}")
    if args.dry_run:
        print("\nDry run completed - no changes were made")

if __name__ == "__main__":
    main()
