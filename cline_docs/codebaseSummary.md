# Codebase Summary

This document provides an overview of the Equipment Loan Agreement System, a web application built with Python and the Django framework. It's designed to manage equipment loan agreements, primarily for users at Purdue University Libraries.

## Key Components and Their Interactions

The project follows a standard Django structure:

*   **`equipment_agreement_project/`**: The main project directory.
    *   `settings.py`: Contains project-wide settings, utilizing `python-decouple` for managing sensitive information and environment-specific configurations via an `.env` file. Key settings include database configuration, installed apps, middleware, template directories, static files configuration, and authentication settings like `LOGIN_REDIRECT_URL`, `LOGOUT_REDIRECT_URL`, and `LOGIN_URL`.
    *   `urls.py`: The root URL configuration file. It routes URL patterns to the appropriate applications, including the `agreement_app` and Django's built-in authentication (`django.contrib.auth.urls`) and admin (`django.contrib.admin`) sites.
    *   `wsgi.py` / `asgi.py`: Entry points for WSGI/ASGI compatible web servers.

*   **`agreement_app/`**: The core application handling the equipment agreement workflow and related functionalities.
    *   **`models.py`**: Defines the database schema.
        *   `CheckinLog`: Stores records of users who have agreed to the terms, including their Purdue ID, user group, and timestamp.
        *   `DebugLogEntry`: Stores application-level log messages for debugging and monitoring purposes.
    *   **`views.py`**: Contains the business logic for handling requests and generating responses.
        *   `index`: Handles the initial form submission for Purdue ID.
        *   `agreement_confirm_view`: Displays user information (currently mocked) and the agreement text, and processes the confirmation. It includes placeholder logic for future Alma API integration for user data retrieval and agreement status updates.
        *   `agreement_success_view`: Displays a success message after the agreement is submitted.
        *   `download_checkin_logs_csv`: A staff-only view that generates and serves a CSV file of all `CheckinLog` entries.
        *   `admin_reports_view`: A staff-only view that displays reports including a monthly usage table, a usage graph (using Chart.js via template), and a check-in calendar.
        *   `session_keep_alive_view`: A view for authenticated users to keep their session active.
    *   **`forms.py`**: Defines forms used in the application.
        *   `PurdueIdForm`: A simple form to collect the user's Purdue ID.
    *   **`templates/`**: Contains HTML templates for rendering views.
        *   `agreement_app/base.html`: The base template providing the overall page structure, including header, navigation (login/logout links, admin links for staff), and content block.
        *   `agreement_app/index.html`: Template for the Purdue ID submission form.
        *   `agreement_app/agreement_confirm.html`: Template to display user details and the agreement text for confirmation.
        *   `agreement_app/agreement_success.html`: Template for the success message after agreement submission.
        *   `agreement_app/admin_reports.html`: Template for displaying admin reports (table, chart, calendar).
        *   `registration/login.html`: Template for the user login page, utilizing Django's authentication system.
    *   **`static/`**: Contains static files for the `agreement_app` (e.g., `css/styles.css`, `images/LSIS_H-Full-RGB_1.jpg`).
    *   **`admin.py`**: Configures the Django admin interface for the `CheckinLog` and `DebugLogEntry` models, allowing staff to view and manage these records.
    *   **`urls.py`**: Defines the URL patterns specific to the `agreement_app`, namespaced as `agreement_app`.

### Authentication System
The application utilizes Django's built-in authentication system (`django.contrib.auth`).
*   **User Model:** Leverages the standard `django.contrib.auth.models.User` model.
*   **Login/Logout:** Uses Django's provided views for login (`accounts/login/`) and logout (`accounts/logout/`).
*   **Access Control:**
    *   `@login_required` decorator is used for views like `session_keep_alive_view` that require any authenticated user.
    *   `@staff_member_required` decorator is used for admin-specific views like `download_checkin_logs_csv` and `admin_reports_view`, ensuring only staff users can access them.

## Data Flow

The application follows Django's MVT (Model-View-Template) architecture.
1.  **Request Handling:** A user request hits a URL defined in `urls.py`.
2.  **URL Dispatching:** Django's URL dispatcher routes the request to the appropriate view function in `agreement_app/views.py`.
3.  **View Logic:**
    *   The view processes the request, interacts with models (e.g., `CheckinLog`, `User`) if necessary, and handles form submissions using Django Forms (`PurdueIdForm`).
    *   Session data (`request.session`) is used to store temporary information like the Purdue ID between steps.
4.  **Template Rendering:** The view selects and renders an HTML template, passing context data (e.g., form instances, query results) to it.
5.  **Response:** The rendered HTML is sent back to the user's browser.

**Core Agreement Workflow:**
1.  User visits the homepage (`/`), which displays the `PurdueIdForm`.
2.  User submits their Purdue ID.
3.  The `index` view validates the ID and stores it in the session, then redirects to the `agreement_confirm` view.
4.  The `agreement_confirm_view` displays (mocked) user data and the agreement text.
5.  User clicks "I Agree".
6.  The `agreement_confirm_view` processes the confirmation, creates a `CheckinLog` entry, a `DebugLogEntry`, (conceptually updates Alma and sends an email), clears the session data, and redirects to the `agreement_success` view.
7.  The `agreement_success_view` displays a success message.

## External Dependencies
*   **Django:** The core web framework.
*   **python-decouple:** Used for managing configuration settings from environment variables and `.env` files.
*   **requests:** Intended for future integration with the Alma API (currently simulated).
*   **Pillow:** Included for image handling, though not explicitly used in the current core agreement workflow beyond displaying a static logo.

## Notes for Future Development
*   Implement actual Alma API integration for user data retrieval and updating user notes.
*   Implement actual email sending functionality (currently uses `console.EmailBackend`).
*   Enhance error handling and user feedback.
*   Add more comprehensive unit and integration tests.
*   Consider adding user registration if non-Purdue users need to be accommodated.
*   Implement pagination for long lists in admin reports or log views.
*   Refine UI/UX based on user feedback.
