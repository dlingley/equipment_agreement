# Equipment Loan Agreement System

This project provides a web application for managing equipment loan agreements, primarily targeting patrons of Purdue University Libraries. It allows users to electronically sign an agreement after their Purdue ID is verified (simulated for now). The system also includes administrative features for viewing reports and managing user data.

The web application is built using Python and the Django framework.

## Features

*   User authentication (Django built-in).
*   Equipment loan agreement form.
*   Confirmation page displaying user details (currently mocked) and agreement text.
*   Success page after agreement submission.
*   Logging of check-ins/agreements.
*   Admin interface for viewing logs.
*   CSV download of check-in logs for staff.
*   Admin reports page with monthly usage table, bar chart, and calendar view.
*   Session keep-alive functionality.
*   Configuration via environment variables using `python-decouple`.

## Requirements

*   Python 3.8+
*   Django ~=5.2 (as specified in `requirements.txt`)
*   python-decouple
*   requests (for planned Alma integration)
*   Pillow (for potential image handling, included as a common Django dependency)
*   See `requirements.txt` for a full list of Python dependencies.

## Getting Started (Django Application)

1.  **Clone the repository:**
    ```bash
    git clone <repository_url>
    cd equipment-loan-agreement-django 
    ```

2.  **Create and activate a virtual environment:**
    *   On macOS/Linux:
        ```bash
        python3 -m venv venv
        source venv/bin/activate
        ```
    *   On Windows:
        ```bash
        python -m venv venv
        .\venv\Scripts\activate
        ```

3.  **Install dependencies:**
    ```bash
    pip install -r requirements.txt
    ```

4.  **Set up environment variables:**
    *   Copy the example environment file:
        ```bash
        cp .env.example .env
        ```
    *   Generate a new `SECRET_KEY`:
        ```bash
        python -c "from django.core.management.utils import get_random_secret_key; print(get_random_secret_key())"
        ```
        Copy the output and replace `'your_secret_key_goes_here'` in your `.env` file.
    *   Ensure `DEBUG=True` in `.env` for development.
    *   Update placeholder values for `ALMA_API_KEY`, `ALMA_API_URL`, and SMTP settings in `.env` if you plan to test these features (note: full Alma/SMTP integration is not yet implemented in the current version but placeholders are present).

5.  **Navigate to the project directory:**
    ```bash
    cd equipment_agreement_project
    ```

6.  **Run database migrations:**
    ```bash
    python manage.py migrate
    ```

7.  **Create a superuser (for admin access):**
    ```bash
    python manage.py createsuperuser
    ```
    Follow the prompts to create an admin user.

8.  **Run the development server:**
    ```bash
    python manage.py runserver
    ```

9.  Access the application at `http://127.0.0.1:8000/` and the admin panel at `http://localhost:8000/admin/`.

## Project Structure

*   `equipment_agreement_project/`: The main Django project directory.
    *   `settings.py`: Project settings, now utilizing `python-decouple`.
    *   `urls.py`: Project-level URL configuration.
*   `agreement_app/`: The Django application handling the agreement workflow.
    *   `models.py`: Defines the `CheckinLog` and `DebugLogEntry` database models.
    *   `views.py`: Contains the logic for handling web requests and rendering templates.
    *   `forms.py`: Defines forms like `PurdueIdForm`.
    *   `urls.py`: App-specific URL configurations.
    *   `templates/`: Contains HTML templates for the application.
        *   `agreement_app/`: Templates for the main agreement workflow.
        *   `registration/`: Templates for authentication (e.g., `login.html`).
    *   `static/`: Contains static files like CSS and images for the `agreement_app`.
*   `manage.py`: Django's command-line utility.
*   `db.sqlite3`: The SQLite database file (default for development).
*   `.env`: Stores environment-specific settings (not version controlled).
*   `.env.example`: Template for the `.env` file.
*   `requirements.txt`: Lists Python package dependencies.
*   `scripts/`: Contains utility scripts.

## Scripts

The following scripts are available in the `scripts/` directory (these are not part of the Django application but are related utility scripts):

*   **`fix_agreements.py`**:
    *   This script is designed to process a CSV export from Alma Analytics, specifically the "Purdue - Alma Users With Blocks" report.
    *   It iterates through the user records, checks for specific block descriptions related to equipment agreements, and updates the user notes in Alma via the API to reflect that the agreement has been signed.
    *   It requires an `config.py` file (or environment variables) with Alma API key and other necessary configurations.
    *   **Usage:** `python fix_agreements.py` (ensure `config.py` is set up or environment variables are defined).

*   **`validate_note_segments.py`**:
    *   This script also processes a CSV export from Alma Analytics ("Purdue - Alma Users With Blocks").
    *   It checks the format and content of user notes, specifically looking for agreement signatures and dates, to identify any inconsistencies or issues.
    *   It outputs a CSV file (`user_notes_validation.csv`) detailing users with missing or malformed agreement notes.
    *   **Usage:** `python validate_note_segments.py /path/to/your/report.csv`

## Note Structure in Alma

The scripts and potentially future integrations rely on a specific structure for user notes in Alma to indicate a signed equipment agreement:

*   **Format:** `ILS Equipment Agreement Signed: YYYY-MM-DD`
*   **Example:** `ILS Equipment Agreement Signed: 2023-10-26`

This format allows for easy parsing and verification of agreement status and date.
