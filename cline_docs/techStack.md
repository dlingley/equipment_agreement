# Technology Stack for Equipment Loan Agreement System

This document outlines the technology stack used in the Equipment Loan Agreement System.

## Frontend Technologies

*   **HTML5:** For structuring the web pages.
*   **CSS3:** For styling the user interface. (Custom styles in `agreement_app/static/agreement_app/css/styles.css`)
*   **JavaScript:**
    *   Vanilla JavaScript for client-side interactions (e.g., calendar display in admin reports).
    *   **Chart.js:** Used for rendering charts on the admin reports page.

## Backend Technologies

*   **Python:** The primary programming language for the application logic.
*   **Django (Version 5.2.1):** The web framework used for building the application. It provides a robust structure for models, views, templates, forms, and more.
*   **python-decouple:** Used for managing configuration settings, allowing separation of configuration from code (e.g., `SECRET_KEY`, database settings, API keys) via a `.env` file.

## Database

*   **SQLite:** Used as the default database for development and simplicity. Django's ORM allows for easy switching to other database backends (e.g., PostgreSQL, MySQL) if needed for production.

## External Integrations (Planned/Conceptual)

*   **Alma API:** Integration for fetching user data and updating user records (currently placeholder/mocked in the views). The `requests` library is included for this purpose.
*   **Email System:** Django's built-in email functionality will be used for sending confirmation emails. Currently configured to use `django.core.mail.backends.console.EmailBackend` for development, with settings available via `.env` to switch to SMTP for production.

## Security Features

The application leverages Django's built-in security features:

*   **Cross-Site Request Forgery (CSRF) Protection:** Django's CSRF middleware and template tags are used to protect against CSRF attacks on forms.
*   **Cross-Site Scripting (XSS) Protection:** Django's template system automatically escapes variables, providing protection against XSS vulnerabilities.
*   **SQL Injection Protection:** Django's ORM uses parameterized queries, which protects against SQL injection vulnerabilities.
*   **Secure Password Handling:** Django's authentication system provides secure password hashing and storage.
*   **Session Management:** Django's session framework is used for secure user session handling.
*   **Clickjacking Protection:** `django.middleware.clickjacking.XFrameOptionsMiddleware` is used to prevent clickjacking.
*   **Security Middleware:** `django.middleware.security.SecurityMiddleware` provides several security enhancements (e.g., SSL redirect, HSTS, content security policy headers if configured).

## System Architecture

The application follows the **Model-View-Template (MVT)** architectural pattern, a variation of MVC:

*   **Model (`models.py`):** Represents the application's data structure. Defines database tables (`CheckinLog`, `DebugLogEntry`) and their relationships. Interacts with the database via Django's ORM.
*   **View (`views.py`):** Contains the request handling logic. It processes incoming HTTP requests, interacts with models to retrieve or manipulate data, and selects a template to render the response.
*   **Template (`templates/` directory):** Defines the presentation layer. HTML files mixed with Django Template Language (DTL) tags to display data dynamically.
*   **URLs (`urls.py`):** Maps URL patterns to views, directing incoming requests to the appropriate handler function.
*   **Forms (`forms.py`):** Handles data input and validation using Django's forms framework.
*   **Static Files (`static/` directory):** Serves CSS, JavaScript, and images.

### Configuration System
*   Project settings are managed in `equipment_agreement_project/settings.py`.
*   Sensitive and environment-specific configurations are loaded from a `.env` file using the `python-decouple` library.

### Logging System
*   **`CheckinLog` Model:** Used to record user agreements and basic visit information.
*   **`DebugLogEntry` Model:** Used for custom application-level logging, viewable through the Django admin interface.
*   Django's built-in logging capabilities can also be configured for more extensive system logging if needed.

## Development & Deployment
*   **Development Server:** Django's built-in development server (`manage.py runserver`) is used for local development and testing.
*   **Version Control:** (Presumably Git, though not explicitly managed by the AI itself).
*   **Dependency Management:** `pip` and `requirements.txt`.

This stack provides a robust and scalable foundation for the Equipment Loan Agreement System.
