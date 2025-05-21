// Session management for persistent logins
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize session monitoring if user is logged in
    // Check for presence of a logged-in specific element
    if (document.querySelector('.header-buttons')) {
        initSessionMonitor();
    }
});

// Constants for session management
const SESSION_CHECK_INTERVAL = 5 * 60 * 1000; // 5 minutes in milliseconds
const KEEPALIVE_URL = 'keepalive.php';

// Variables to track session state
let sessionMonitorInterval = null;
let wasHidden = false;

/**
 * Initialize session monitoring functionality
 * Sets up the interval timer and visibility change handlers
 */
function initSessionMonitor() {
    // Start periodic session refresh
    sessionMonitorInterval = setInterval(refreshSession, SESSION_CHECK_INTERVAL);
    
    // Set up visibility change detection
    setupVisibilityHandlers();
    
    // Initial session refresh
    refreshSession();
}

/**
 * Set up handlers for page visibility changes
 * This helps handle computer sleep/wake cycles
 */
function setupVisibilityHandlers() {
    // Handle visibility change events
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            wasHidden = true;
            clearInterval(sessionMonitorInterval);
        } else if (wasHidden) {
            wasHidden = false;
            refreshSession();
            sessionMonitorInterval = setInterval(refreshSession, SESSION_CHECK_INTERVAL);
        }
    });

    // Additional event listeners for better sleep/wake detection
    window.addEventListener('focus', handleWake);
    window.addEventListener('blur', handleSleep);
    window.addEventListener('online', handleWake);
    window.addEventListener('offline', handleSleep);
}

/**
 * Handle computer/browser wake events
 * Restarts session monitoring and refreshes immediately
 */
function handleWake() {
    if (wasHidden) {
        wasHidden = false;
        refreshSession();
        
        // Restart the interval if it was cleared
        if (!sessionMonitorInterval) {
            sessionMonitorInterval = setInterval(refreshSession, SESSION_CHECK_INTERVAL);
        }
    }
}

/**
 * Handle computer/browser sleep events
 * Cleans up the interval to prevent queue-up
 */
function handleSleep() {
    wasHidden = true;
    if (sessionMonitorInterval) {
        clearInterval(sessionMonitorInterval);
        sessionMonitorInterval = null;
    }
}

/**
 * Refresh the session by calling the keepalive endpoint
 * This updates the last activity timestamp and regenerates the session ID if needed
 */
function refreshSession() {
    fetch(KEEPALIVE_URL, {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json'
        },
        cache: 'no-cache'
    })
    .then(response => {
        // First check if response is ok
        if (!response.ok) {
            // If session is invalid, reload the page to trigger login redirect
            if (response.status === 401) {
                console.warn('Session expired, reloading page...');
                window.location.reload();
                return;
            }
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        // Try to parse the JSON response
        return response.json();
    })
    .then(data => {
        if (data.status === 'success') {
            console.debug('Session refreshed successfully at:', new Date(data.timestamp * 1000).toISOString());
        } else {
            console.warn('Session refresh returned unexpected status:', data.status);
        }
    })
    .catch(error => {
        // Check if it's a JSON parsing error
        if (error instanceof SyntaxError && error.message.includes('JSON')) {
            console.error('Server returned invalid JSON. This might be a server error:', error);
            // Log additional debug info
            error.response?.text().then(text => {
                console.error('Server response:', text);
            }).catch(e => {
                console.error('Could not read server response:', e);
            });
        } else {
            console.error('Error refreshing session:', error);
        }
        // Don't clear interval on network errors - will retry on next interval
    });
}
