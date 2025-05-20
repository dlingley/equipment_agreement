# Technology Stack Documentation

## Frontend Technologies
- HTML5: Core markup language
- CSS3: Styling and responsive design
  - Custom styles defined in styles.css
  - Responsive design for various screen sizes
  - Custom styling for forms and error messages
- JavaScript: Minimal client-side functionality

## Backend Technologies
- PHP: Server-side scripting language
  - Session-based authentication system
  - Custom logging infrastructure
  - Form processing and validation
  - Error handling and debugging capabilities
  - POSIX function compatibility layer
  - Timezone management system with Eastern Time standardization

## External Integrations
### Alma API Integration
- ExLibris Alma API for user management
- RESTful API communication
- Full user data retrieval and updates
- Configured endpoints for user operations

### Email System
- SMTP integration via Purdue's email server
- Automated notification capabilities
- System-generated communications

## Security Features
- Session-based authentication
  - Secure session management
  - Login state verification
  - Role-based access control (Admin/Regular users)
  - Configurable session timeout
  - Secure cookie settings
- Form validation and sanitization
- Error handling and logging
- Configurable debug mode
- Timezone-aware logging system

## System Architecture
### Core Components
- Configuration System
  - config.php: Central configuration management
  - Environment-specific settings
  - API configurations
  - SMTP settings
  - Session parameters
  - Timezone settings (America/New_York)
- Logging System
  - Custom Logger class
  - Multiple log types (debug, checkin)
  - Configurable log paths
  - Debug mode support
  - Timezone-aware timestamp generation
  - Eastern Time standardization

### File Structure
- admin.php: Administrative interface
- login.php: User authentication
- confirm.php: Agreement confirmation with timezone handling
- success.php: Success handling
- index.php: Main entry point
- config.php: Configuration settings
- styles.css: Global styling

### Logging System Details
- Multi-level logging architecture
  - Debug logging with timezone awareness
  - Check-in logging with Eastern Time timestamps
  - Error logging with standardized time formats
- Fallback mechanisms
  - Alternative log paths
  - Error handler integration
  - System logging capabilities
- Configuration-driven settings
  - Timezone configuration
  - Log path definitions
  - Debug mode control

## Development Environment
- Local PHP development server
- Error reporting configuration
  - E_ALL error reporting in development
  - Configurable display_errors setting
- Version control: Git
- Debug mode for development testing
- Timezone database support

## Data Management
- Session-based data storage
- Form data processing
- User role management
- Agreement tracking
- API-based user data retrieval
- Time-based logging and tracking

## Security Measures
- Session protection
  - Configurable timeout
  - Secure cookie settings
  - HTTP-only cookies
- Input validation
- Error handling
- Secure redirects
- Role-based access control
- Time-sensitive operations security

## Future Considerations
- Database integration for agreement storage
- Enhanced logging capabilities
- API development for external integrations
- Enhanced security measures
  - Environment variable usage for sensitive data
  - CSRF protection
  - XSS prevention
  - Input sanitization improvements
  - Secure credential storage
- Advanced time management features
  - Automated timezone updates
  - DST handling improvements
  - Timestamp format standardization
