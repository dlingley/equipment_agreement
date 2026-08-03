# Purdue Libraries - Knowledge Lab Equipment Agreement System

A PHP and Python application for managing equipment checkout agreements at the Purdue University Libraries Knowledge Lab. The system integrates with the Ex Libris Alma User REST API to record user agreement notes, track user visits, log check-in activity, and provide an interactive administrative dashboard with analytics and reporting.

---

## Features

- **User Agreement Flow (`index.php`, `confirm.php`)**:
  - Purdue ID lookup via Alma REST API.
  - Agreement display and submission with real-time update of Alma user notes (`Agreed to Knowledge Lab User Agreement`).
  - Confirmation emails sent via SMTP (PHPMailer).
  - Robust check-in logging (`logs/checkin_log.json`) and detailed debug logging (`logs/debug.log`).

- **Admin Analytics Dashboard (`admin.php`)**:
  - **Summary Cards**: Today's check-ins, monthly check-ins, unique monthly visitors, and top department.
  - **Calendar Heat Map**: Interactive day-by-day check-in intensity visualization with month navigation.
  - **Trend Charts (Chart.js)**: Check-ins by User Group, Top 10 Departments, and Year-over-Year comparisons.
  - **Tabbed Reports**: Usage by User Group, Department breakdown, and Year-over-Year summary tables.
  - **Log Management**: Full log viewer with search/pagination, entry editing/deletion, CSV data export, and debug log viewer.

- **Session Security & Keepalive (`session.js`, `keepalive.php`)**:
  - Sliding session heartbeat to prevent premature timeout on kiosk displays and admin sessions.
  - CSRF protection, input sanitization, and isolated session storage (`/var/tmp/equipment_agreement_sessions`).

- **Automated & Hardened Log Rotation**:
  - Self-maintaining log rotation in `admin.php` automatically moves past months' check-ins into `logs/archives/checkin_YYYY_MM.json`.
  - Data-loss prevention safeguards ensure entries are only pruned from the main log when archive writes succeed.
  - Built-in permission repair ensures log entries remain accessible across web server and CLI processes.

---

## System Requirements

- **PHP**: 7.4 or 8.x with `curl`, `xml`, `json`, `mbstring`, and `fileinfo` extensions enabled
- **Web Server**: Apache 2.4+ / Nginx / IIS
- **Python**: 3.8+ with `requests`, `pytz` modules installed
- **Alma API Key**: Production API key with read/write permissions for Users (`/almaws/v1/users/`)

---

## Configuration

All system configuration settings are centralized in [config.php](file:///Volumes/alma$/equipment_agreement/config.php):

```php
return [
    // Alma API Credentials & Base Endpoints
    'ALMA_API_KEY' => 'YOUR_ALMA_API_KEY',
    'ALMA_API_CONFIG' => [
        'BASE_URL' => 'https://api-na.hosted.exlibrisgroup.com/almaws/v1/users/',
        'GET_PARAMS' => '?view=full&expand=none',
        'PUT_PARAMS' => '?generate_password=false&send_pin_number_letter=false&recalculate_roles=false'
    ],

    // SMTP Configuration for Email Notifications
    'SMTP_CONFIG' => [
        'HOST' => 'smtp.purdue.edu',
        'PORT' => 25,
        'FROM_EMAIL' => 'noreply@purdue.edu',
        'FROM_NAME' => 'Purdue Libraries Knowledge Lab'
    ],

    // Log Paths
    'LOG_PATHS' => [
        'DEBUG' => 'logs/debug.log',
        'CHECKIN' => 'logs/checkin_log.json'
    ],

    // Session & Timezone Configuration
    'TIMEZONE' => 'America/Indianapolis',
    'SESSION_CONFIG' => [
        'TIMEOUT' => 43200, // 12 hours
        'SAVE_PATH' => '/var/tmp/equipment_agreement_sessions'
    ],

    // Admin & Kiosk Auth Credentials
    'ADMIN_USERNAME' => 'equipmentadmin',
    'ADMIN_PASSWORD' => '...',
    'USER_USERNAME' => 'equipuser',
    'USER_PASSWORD' => '...'
];
```

---

## Directory & File Permissions

To ensure both web server processes (`www-data` or IIS app pool) and CLI scripts can read and write log files, enforce the following permissions:

```bash
# Set directory permissions
chmod 775 logs logs/archives

# Set file permissions for log files
chmod 666 logs/*.json logs/archives/*.json logs/*.log
```

---

## Utility & Maintenance Scripts

### Data Recovery & Log Repair

- **`recover_checkins.php`**: Scans `logs/debug.log` for valid JSON check-in events missing from the main log or archives (e.g. following permission lockouts) and safely restores them to their corresponding monthly archive file (`logs/archives/checkin_YYYY_MM.json`) or main log:
  ```bash
  php recover_checkins.php
  ```

- **`cleanup_and_recount.php`**: Performs master cleanup, normalizes 10-digit Purdue IDs, deduplicates check-ins within 5-minute windows, recalculates visit counts, and regenerates clean master log files:
  ```bash
  php cleanup_and_recount.php
  ```

### Alma User Note Utilities (Python)

- **`fix_agreements.py`**: Retroactively adds missing agreement notes in Alma for checked-in users:
  ```bash
  # Dry-run preview
  python3 fix_agreements.py --dry-run

  # Apply changes
  python3 fix_agreements.py
  ```

- **`validate_note_segments.py`**: Ensures all Alma agreement notes reside in the `Internal` segment:
  ```bash
  # Dry-run preview
  python3 validate_note_segments.py --dry-run

  # Apply changes
  python3 validate_note_segments.py
  ```

---

## Alma User Note Structure

Agreement notes added to Alma user records follow this standard structure:

- **Segment Type**: `Internal`
- **Note Type**: `CIRCULATION`
- **Note Text**: `Agreed to Knowledge Lab User Agreement`
- **User Viewable**: `true`
- **Popup Note**: `true`
