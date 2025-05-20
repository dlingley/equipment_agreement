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
    """Check if user already has the agreement note."""
    try:
        root = ET.fromstring(user_xml)
        notes = root.findall(".//user_note")
        for note in notes:
            note_text = note.find("note_text")
            if note_text is not None and "Agreed to Knowledge Lab User Agreement" in note_text.text:
                return True
    except ET.ParseError as e:
        print(f"Error parsing user XML: {str(e)}")
    return False

def add_agreement_note(user_xml):
    """Add agreement note to user XML."""
    try:
        root = ET.fromstring(user_xml)
        
        # Remove roles section to prevent conflicts
        roles = root.find(".//user_roles")
        if roles is not None:
            root.remove(roles)
        
        # Find or create user_notes section
        user_notes = root.find(".//user_notes")
        if user_notes is None:
            user_notes = ET.SubElement(root, "user_notes")
        
        # Create new note
        note = ET.SubElement(user_notes, "user_note")
        note.set("segment_type", "Internal")
        
        note_type = ET.SubElement(note, "note_type")
        note_type.text = "CIRCULATION"
        
        note_text = ET.SubElement(note, "note_text")
        note_text.text = "Agreed to Knowledge Lab User Agreement"
        
        user_viewable = ET.SubElement(note, "user_viewable")
        user_viewable.text = "true"
        
        popup_note = ET.SubElement(note, "popup_note")
        popup_note.text = "true"
        
        return ET.tostring(root, encoding='unicode')
    except Exception as e:
        print(f"Error modifying user XML: {str(e)}")
        return None

def process_users(config, users, dry_run=False):
    """Process users and add agreement notes where missing."""
    session = requests.Session()
    results = {'processed': 0, 'updated': 0, 'skipped': 0, 'failed': 0}
    
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
            
            # Check if agreement exists
            if check_user_agreement(response.text):
                print(f"User {user_id} already has agreement note")
                results['skipped'] += 1
                continue
            
            # In dry run mode, just report what would be done
            if dry_run:
                print(f"Would add agreement note to user {user_id}")
                continue
            
            # Add agreement note
            modified_xml = add_agreement_note(response.text)
            if not modified_xml:
                print(f"Failed to modify XML for user {user_id}")
                results['failed'] += 1
                continue
            
            # PUT updated user data
            put_url = f"{config['base_url']}{user_id}?generate_password=false&send_pin_number_letter=false&recalculate_roles=false&apikey={config['api_key']}"
            put_response = session.put(put_url, data=modified_xml, headers={'Content-Type': 'application/xml'})
            
            if put_response.status_code == 200:
                print(f"Successfully updated user {user_id}")
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
    parser = argparse.ArgumentParser(description='Fix missing agreement notes in Alma')
    parser.add_argument('--dry-run', action='store_true', help='Preview changes without making them')
    args = parser.parse_args()
    
    print("Starting agreement note fix script...")
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
    print(f"Users to be added: {results['updated']}")
    print(f"Users already with note: {results['skipped']}")
    print(f"Users with errors: {results['failed']}")
    if args.dry_run:
        print("\nDry run completed - no changes were made")

if __name__ == "__main__":
    main()
