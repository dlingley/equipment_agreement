# Active Context

## Current Focus

### Agreement Note Management
1. Note Segment Validation
   - Created validate_note_segments.py script
   - Checking existing agreement notes
   - Updating notes to Internal segment
   - Ensuring consistent note placement

2. System Standardization
   - Eastern Time (America/Indianapolis) standardization
   - Consistent timestamp formats
   - Debug log synchronization
   - Check-in log alignment

## Recent Changes

### Kiosk Session Timeout Fix (Heartbeat & Storage Isolation)
- **Keepalive for Kiosk Users:** Modified `session.js` to run the heartbeat check on any page without `.login-form` (so regular kiosk users on `index.php` are successfully kept active).
- **Session File Isolation:** Added `SAVE_PATH` (`__DIR__ . '/sessions'`) to configuration and created `sessions/` directory. Added a robust `.htaccess` configuration to deny direct web access. This isolates sessions from default system-wide PHP garbage collection.
- **PHP Session Lifecycle Corrected:** Fixed a critical order-of-operations gotcha in multiple PHP entry points where `session_start()` was called before session configurations were defined. All session variables and paths are now fully configured prior to initialization.
- **Increased Timeout baseline:** Raised `TIMEOUT` and `COOKIE_LIFETIME` from 2 hours (`7200` seconds) to 12 hours (`43200` seconds) in `config.php`.

### Git Housekeeping & Repository Cleanup
- **Deprecated File Deletion:** Removed obsolete log management files (`cron_setup.txt`, `manage_logs.log`, `manage_logs.py`, `manage_logs.sh`) and committed the deletions to clean up the repository.
- **Cross-Platform Git Setting**: Configured `git config core.filemode false` to prevent file permission differences across the SMB network mount from triggering unstaged file changes (e.g., `styles.css`).
- **macOS Double-Files Ignored**: Updated `.gitignore` to ignore `._*` AppleDouble resource forks and `.DS_Store` files to keep the local and remote environment clean.

### Note Validation System
- New validate_note_segments.py script added
- Agreement note segment validation
- Dry run capability implemented
- Detailed logging and reporting
- Progress tracking during updates

### Agreement Note Structure
- Segment Type: Internal
- Note Type: CIRCULATION
- Note Text: "Agreed to Knowledge Lab User Agreement"
- User Viewable: true
- Popup Note: true

### Documentation Updates
- README.md enhanced with script usage
- Note structure documentation added
- Command examples provided
- Requirements updated

## Active Decisions

### Session Security & Reliability
1. **Isolated Session Directory**
   - Decision: Route all active PHP session files to a dedicated, restricted `sessions/` folder rather than `/tmp`.
   - Reason: Standard PHP garbage collection on multi-host servers sweeps every 24 minutes and destroys active sessions in shared folders. Storing them locally keeps them active for the full 12 hours.
   - Status: Complete

2. **Heartbeat Initialization**
   - Decision: Check for the absence of `.login-form` rather than the presence of `.header-buttons` in JS.
   - Reason: Kiosk users do not have admin header buttons, meaning the heartbeat was silently bypassed. Removing this restriction triggers keepalives for both regular users and admins.
   - Status: Complete

### Note Management
1. Note Segment Location
   - Decision: Move all notes to Internal segment
   - Reason: Consistency and proper visibility
   - Status: Implementation complete
   - Priority: Completed

2. Validation Process
   - Decision: Create separate validation script
   - Reason: Clean separation of concerns
   - Status: Implemented
   - Priority: Completed

## Current Considerations

### Immediate Priorities
1. System Updates
   - Monitor note segment updates
   - Track validation results
   - Ensure proper note placement
   - Verify user data integrity

2. Documentation
   - Keep README updated
   - Document note structure
   - Maintain usage guides
   - Track system changes

## Next Steps

### Short Term
1. Session Monitoring
   - [x] Create local `sessions/` directory with `.htaccess` security
   - [x] Correct PHP script ordering for session settings
   - [x] Deploy fixed `session.js` keepalive
   - [ ] Monitor sessions in production to ensure they persist over 8+ hours
   - [ ] Check logs for any session regeneration errors

2. Note Management
   - [x] Create validation script
   - [x] Test with dry run
   - [x] Update documentation
   - [ ] Monitor results

3. System Updates
   - [ ] Review error handling
   - [ ] Update logging if needed
   - [ ] Consider additional validations
   - [ ] Track system performance

## Critical Notes

### System Status
- Core functionality operational
- Logging system active
- Session management working
- New validation script added
- Note segment updates working
- Documentation current

### Known Issues
1. Visit Tracking
   - Visit tracking occurs after getting user data
   - Accurate user group in log entries
   - Duplicate visit prevention within 30 seconds
   - Visit counts tracked consistently

2. Error Tracking
   - AUTH_ERROR: Failed session authentication
   - API_ERROR: cURL and API communication issues
   - INVALID_ID: User not found in Alma
   - INVALID_RESPONSE: Malformed XML responses
   - AGREEMENT_UPDATE: Failed agreement updates

## Environment Details

### Current Configuration
- PHP 7.0+ environment
- Python 3.x with requests module
- Apache web server
- Eastern Time zone
- Debug mode available

### Active Systems
- Logger operational
- Session management active
- Form processing working
- API integration functional
- Note validation system active
- Agreement management functional
