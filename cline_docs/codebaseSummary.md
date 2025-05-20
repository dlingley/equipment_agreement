# Codebase Summary

## Key Components and Their Interactions

### Authentication System
- login.php: Handles user authentication
- logout.php: Manages session termination
- Session-based state management
  - Tracks user login status
  - Maintains user type (admin/regular)
  - Stores temporary form data

### Core Application Components
- index.php: 
  - Main entry point
  - Equipment agreement form
  - Session validation
  - Purdue ID collection
  - Error handling and display
  - Debug information (when enabled)
  - Timezone management
- confirm.php: Agreement confirmation handling with timezone-aware logging
- success.php: Success page after agreement submission
- admin.php: Administrative interface
- config.php: Central configuration management

### Logging Infrastructure
#### Logger Class (in index.php)
- Hierarchical log path selection
- Multiple fallback mechanisms
- Error handling integration
- Debug mode support
- Status reporting capabilities
- Timezone-aware logging
  - Uses America/New_York timezone
  - Consistent timestamp formats
  - Config-driven timezone settings

### Time Management System
- Centralized timezone configuration in config.php
- Consistent timezone setting across all files
- Eastern Time (America/New_York) timestamps
- Timezone-aware features:
  - Check-in logs
  - Debug logging
  - Error reporting
  - Session timestamps

### Asset Components
- styles.css: Global styling definitions
  - Form styling
  - Error message formatting
  - Responsive design rules
- LSIS_H-Full-RGB_1.jpg: Purdue Libraries logo

## Data Flow

### Authentication Flow
1. Initial access redirects to login.php if not authenticated
2. Successful login sets session variables
3. Session validation on each page access
4. Logout clears session state

### Agreement Processing Flow
1. User Authentication:
   - Session verification
   - Role validation
2. Form Submission:
   - Purdue ID collection
   - Validation checks
   - Error handling
3. Confirmation Process:
   - Data verification
   - Agreement presentation
4. Completion:
   - Success page display
   - Session cleanup

### Logging Flow
1. Timestamp Generation:
   - Timezone verification
   - Eastern Time conversion
   - Format standardization
2. Log Writing:
   - Debug logs
   - Check-in records
   - Error logging
3. Data Recording:
   - User activity tracking
   - Visit counting
   - Agreement status

### Error Handling Flow
1. Error Detection:
   - Form validation
   - Session checks
   - System errors
2. Error Processing:
   - Logging via Logger class
   - Session storage
   - Timezone-aware timestamps
3. User Notification:
   - Error display
   - Automatic refresh
   - Redirect handling

## External Dependencies
- PHP Core Functions
- POSIX Functions (with fallbacks)
- Session Management
- Error Reporting System
- Timezone Database

## Recent Significant Changes
- Initial documentation structure established
- Detailed logging system documented
- Authentication flow mapped
- Error handling processes documented
- Timezone standardization implemented
  - All timestamps now in Eastern Time
  - Consistent time handling across system
  - Debug and check-in log synchronization

## Component Relationships
### Frontend Layer
- HTML structure with PHP integration
- CSS styling system
- Form handling
- Error display
- Debug information panel

### Backend Layer
- Session management
- Logging system with timezone awareness
- Configuration handling
- Form processing
- Error handling
- Role-based access control

## Notes for Future Development
- Consider implementing database storage
- Enhance error reporting
- Add CSRF protection
- Implement input sanitization
- Consider API development
- Add automated testing
- Monitor timezone handling during daylight saving changes

## Recent Updates
- Documented logging system architecture
- Mapped authentication flow
- Detailed error handling processes
- Identified security considerations
- Implemented timezone standardization
- Synchronized all timestamp generations
