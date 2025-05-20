#!/bin/bash

# Get the directory of the script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Navigate to the script directory
cd "$SCRIPT_DIR"

# Configuration
MAX_SIZE=10          # Maximum log size in MB
MAX_BACKUPS=5        # Number of backup files to keep
RETENTION_DAYS=30    # Days to keep archived logs
LOG_FILE="manage_logs.log"

# Run the Python script with configuration
echo "$(date '+%Y-%m-%d %H:%M:%S') Starting log management..." >> "$LOG_FILE"
python3 manage_logs.py \
    --max-size "$MAX_SIZE" \
    --max-backups "$MAX_BACKUPS" \
    --retention-days "$RETENTION_DAYS" \
    >> "$LOG_FILE" 2>&1

# Check if the script executed successfully
if [ $? -eq 0 ]; then
    echo "$(date '+%Y-%m-%d %H:%M:%S') Log management completed successfully" >> "$LOG_FILE"
else
    echo "$(date '+%Y-%m-%d %H:%M:%S') Error: Log management failed" >> "$LOG_FILE"
fi

# Make sure log file doesn't grow too large
if [ -f "$LOG_FILE" ]; then
    # Keep only last 1000 lines
    tail -n 1000 "$LOG_FILE" > "$LOG_FILE.tmp" && mv "$LOG_FILE.tmp" "$LOG_FILE"
fi
