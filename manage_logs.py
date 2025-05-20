#!/usr/bin/env python3
import os
import shutil
import datetime
import argparse
import logging
import gzip
from pathlib import Path

class LogManager:
    def __init__(self, config):
        """Initialize the LogManager with configuration."""
        self.config = config
        self.setup_logging()

    def setup_logging(self):
        """Configure logging for the log manager itself."""
        logging.basicConfig(
            level=logging.INFO,
            format='[%(asctime)s] [%(levelname)s] %(message)s',
            datefmt='%Y-%m-%d %H:%M:%S'
        )

    def get_file_size_mb(self, file_path):
        """Get file size in megabytes."""
        try:
            return os.path.getsize(file_path) / (1024 * 1024)
        except OSError as e:
            logging.error(f"Error getting file size for {file_path}: {e}")
            return 0

    def should_rotate(self, file_path):
        """Check if file should be rotated based on size."""
        return self.get_file_size_mb(file_path) >= self.config['MAX_SIZE_MB']

    def get_archive_name(self, file_path):
        """Generate archive name with timestamp."""
        base_name = os.path.basename(file_path)
        timestamp = datetime.datetime.now().strftime('%Y%m%d_%H%M%S')
        return f"{os.path.splitext(base_name)[0]}_{timestamp}{os.path.splitext(base_name)[1]}"

    def compress_file(self, file_path, archive_path):
        """Compress a file using gzip."""
        try:
            with open(file_path, 'rb') as f_in:
                with gzip.open(f"{archive_path}.gz", 'wb') as f_out:
                    shutil.copyfileobj(f_in, f_out)
            return True
        except Exception as e:
            logging.error(f"Error compressing {file_path}: {e}")
            return False

    def rotate_debug_log(self, file_path):
        """Rotate debug log using copy-truncate strategy."""
        try:
            # Create archives directory if it doesn't exist
            archive_dir = os.path.join(os.path.dirname(file_path), 'archives')
            os.makedirs(archive_dir, exist_ok=True)

            # Generate archive path
            archive_path = os.path.join(archive_dir, self.get_archive_name(file_path))

            # Copy current log to archive
            shutil.copy2(file_path, archive_path)

            # Compress the archive if configured
            if self.config['COMPRESS']:
                if self.compress_file(archive_path, archive_path):
                    os.remove(archive_path)  # Remove uncompressed archive
                else:
                    logging.error(f"Failed to compress {archive_path}")

            # Truncate the current log file
            with open(file_path, 'w') as f:
                f.truncate(0)

            logging.info(f"Successfully rotated debug log: {file_path}")
            return True
        except Exception as e:
            logging.error(f"Error rotating debug log {file_path}: {e}")
            return False

    def rotate_checkin_log(self, file_path):
        """Rotate checkin log using consolidation strategy."""
        try:
            # Create archives directory if it doesn't exist
            archive_dir = os.path.join(os.path.dirname(file_path), 'archives')
            os.makedirs(archive_dir, exist_ok=True)

            # Read current log content
            with open(file_path, 'r') as f:
                content = f.readlines()

            # Group entries by month
            entries_by_month = {}
            for line in content:
                parts = line.strip().split(',')
                if len(parts) >= 2:
                    try:
                        timestamp = datetime.datetime.strptime(parts[1], '%Y-%m-%d %H:%M:%S')
                        month_key = timestamp.strftime('%Y_%m')
                        if month_key not in entries_by_month:
                            entries_by_month[month_key] = []
                        entries_by_month[month_key].append(line)
                    except (ValueError, IndexError) as e:
                        logging.warning(f"Skipping malformed line: {line.strip()}, Error: {e}")

            # Save each month to its own archive file
            current_month = datetime.datetime.now().strftime('%Y_%m')
            for month, entries in entries_by_month.items():
                if month != current_month:  # Don't archive current month
                    archive_path = os.path.join(archive_dir, f"checkin_{month}.csv")
                    with open(archive_path, 'a') as f:
                        f.writelines(entries)

            # Keep only current month in active log
            if current_month in entries_by_month:
                with open(file_path, 'w') as f:
                    f.writelines(entries_by_month[current_month])
            else:
                with open(file_path, 'w') as f:
                    f.truncate(0)

            logging.info(f"Successfully rotated checkin log: {file_path}")
            return True
        except Exception as e:
            logging.error(f"Error rotating checkin log {file_path}: {e}")
            return False

    def cleanup_old_archives(self, log_path):
        """Remove archives older than retention period."""
        try:
            archive_dir = os.path.join(os.path.dirname(log_path), 'archives')
            if not os.path.exists(archive_dir):
                return

            retention_date = datetime.datetime.now() - datetime.timedelta(days=self.config['RETENTION_DAYS'])

            for item in os.listdir(archive_dir):
                item_path = os.path.join(archive_dir, item)
                if not os.path.isfile(item_path):
                    continue

                # Extract date from filename or use file modification time
                try:
                    if 'debug_' in item:
                        date_str = item.split('_')[1].split('.')[0]  # Extract YYYYMMDD
                        file_date = datetime.datetime.strptime(date_str, '%Y%m%d')
                    elif 'checkin_' in item:
                        date_str = item.split('_')[1].split('.')[0]  # Extract YYYY_MM
                        file_date = datetime.datetime.strptime(date_str, '%Y_%m')
                    else:
                        file_date = datetime.datetime.fromtimestamp(os.path.getmtime(item_path))
                except ValueError:
                    file_date = datetime.datetime.fromtimestamp(os.path.getmtime(item_path))

                if file_date < retention_date:
                    os.remove(item_path)
                    logging.info(f"Removed old archive: {item}")

        except Exception as e:
            logging.error(f"Error cleaning up old archives: {e}")

    def manage_logs(self):
        """Main function to manage all logs."""
        for log_type, path in self.config['LOG_PATHS'].items():
            if not os.path.exists(path):
                logging.warning(f"Log file does not exist: {path}")
                continue

            if self.should_rotate(path):
                logging.info(f"Rotating {log_type} log: {path}")
                if log_type == 'DEBUG':
                    self.rotate_debug_log(path)
                elif log_type == 'CHECKIN':
                    self.rotate_checkin_log(path)

            # Clean up old archives
            self.cleanup_old_archives(path)

def main():
    parser = argparse.ArgumentParser(description='Log file management script')
    parser.add_argument('--max-size', type=int, default=10,
                      help='Maximum log size in MB before rotation (default: 10)')
    parser.add_argument('--max-backups', type=int, default=5,
                      help='Number of backup files to keep (default: 5)')
    parser.add_argument('--retention-days', type=int, default=30,
                      help='Number of days to keep archived logs (default: 30)')
    parser.add_argument('--no-compress', action='store_true',
                      help='Disable compression of archived logs')
    args = parser.parse_args()

    # Configuration
    config = {
        'MAX_SIZE_MB': args.max_size,
        'MAX_BACKUPS': args.max_backups,
        'RETENTION_DAYS': args.retention_days,
        'COMPRESS': not args.no_compress,
        'LOG_PATHS': {
            'DEBUG': 'debug.log',
            'CHECKIN': 'checkin.log'
        }
    }

    # Initialize and run log manager
    log_manager = LogManager(config)
    log_manager.manage_logs()

if __name__ == '__main__':
    main()
