from django.test import TestCase, Client
from django.urls import reverse
from django.contrib.auth.models import User
from .models import CheckinLog, DebugLogEntry
from .forms import PurdueIdForm
from django.utils import timezone
import time
import csv
from io import StringIO
import json # Import the json module

class ModelTests(TestCase):
    def test_create_checkin_log(self):
        """Test creating a CheckinLog instance."""
        log_entry = CheckinLog.objects.create(
            purdue_id="testuser123",
            user_group="Student"
        )
        self.assertEqual(CheckinLog.objects.count(), 1)
        retrieved_log = CheckinLog.objects.get(pk=log_entry.pk)
        self.assertEqual(retrieved_log.purdue_id, "testuser123")
        self.assertEqual(retrieved_log.user_group, "Student")
        self.assertIsNotNone(retrieved_log.timestamp)

    def test_create_debug_log_entry(self):
        """Test creating a DebugLogEntry instance."""
        debug_entry = DebugLogEntry.objects.create(
            level="INFO",
            message="This is a test debug message."
        )
        self.assertEqual(DebugLogEntry.objects.count(), 1)
        retrieved_entry = DebugLogEntry.objects.get(pk=debug_entry.pk)
        self.assertEqual(retrieved_entry.level, "INFO")
        self.assertEqual(retrieved_entry.message, "This is a test debug message.")
        self.assertIsNotNone(retrieved_entry.timestamp)

    def test_checkin_log_str(self):
        """Test the __str__ method of CheckinLog."""
        log_entry = CheckinLog.objects.create(purdue_id="testuser123", user_group="Student")
        # To ensure consistent timestamp formatting, especially for microseconds
        expected_str = f"{log_entry.purdue_id} - {log_entry.timestamp.strftime('%Y-%m-%d %H:%M:%S')}"
        self.assertEqual(str(log_entry), expected_str)

    def test_debug_log_entry_str(self):
        """Test the __str__ method of DebugLogEntry."""
        debug_entry = DebugLogEntry.objects.create(level="DEBUG", message="Another test message")
        expected_str = f"{debug_entry.timestamp.strftime('%Y-%m-%d %H:%M:%S')} [DEBUG]"
        self.assertEqual(str(debug_entry), expected_str)

class FormTests(TestCase):
    def test_purdue_id_form_valid(self):
        """Test that the PurdueIdForm is valid with correct data."""
        form_data = {'purdue_id': '1234567890'}
        form = PurdueIdForm(data=form_data)
        self.assertTrue(form.is_valid())

    def test_purdue_id_form_empty(self):
        """Test that the PurdueIdForm is invalid with empty data."""
        form_data = {'purdue_id': ''}
        form = PurdueIdForm(data=form_data)
        self.assertFalse(form.is_valid())
        self.assertIn('purdue_id', form.errors)
        self.assertEqual(form.errors['purdue_id'], ['This field is required.'])

class CoreWorkflowViewTests(TestCase):
    def setUp(self):
        self.client = Client()
        self.user = User.objects.create_user(username='testuser', password='testpassword') # Not used for staff-only views here

    def test_agreement_index_view_get(self):
        """Test GET request to agreement_index view."""
        response = self.client.get(reverse('agreement_app:agreement_index'))
        self.assertEqual(response.status_code, 200)
        self.assertTemplateUsed(response, 'agreement_app/index.html')
        self.assertIsInstance(response.context['form'], PurdueIdForm)

    def test_agreement_index_view_post(self):
        """Test POST to agreement_index with valid ID; check session and redirect."""
        response = self.client.post(reverse('agreement_app:agreement_index'), {'purdue_id': '1234567890'})
        self.assertEqual(response.status_code, 302) # Check for redirect
        self.assertEqual(response.url, reverse('agreement_app:agreement_confirm'))
        self.assertEqual(self.client.session['purdue_id'], '1234567890')

    def test_agreement_confirm_view_get_no_session(self):
        """Test GET to agreement_confirm without purdue_id in session (should redirect to index)."""
        response = self.client.get(reverse('agreement_app:agreement_confirm'))
        self.assertRedirects(response, reverse('agreement_app:agreement_index'))

    def test_agreement_confirm_view_get_with_session(self):
        """Set purdue_id in session, then GET agreement_confirm; check template and context."""
        session = self.client.session
        session['purdue_id'] = 'testpuid'
        session.save()
        response = self.client.get(reverse('agreement_app:agreement_confirm'))
        self.assertEqual(response.status_code, 200)
        self.assertTemplateUsed(response, 'agreement_app/agreement_confirm.html')
        self.assertEqual(response.context['purdue_id'], 'testpuid')
        self.assertIn('user_data', response.context)

    def test_agreement_confirm_view_post(self):
        """Set purdue_id in session, then POST to agreement_confirm; check redirect and session cleared."""
        session = self.client.session
        session['purdue_id'] = 'testpuid'
        session.save()
        
        response = self.client.post(reverse('agreement_app:agreement_confirm'), {'confirm_agreement': '1'})
        self.assertEqual(response.status_code, 302)
        self.assertEqual(response.url, reverse('agreement_app:agreement_success'))
        self.assertNotIn('purdue_id', self.client.session) # Check session is cleared
        self.assertTrue(CheckinLog.objects.filter(purdue_id='testpuid').exists())
        self.assertTrue(DebugLogEntry.objects.filter(message='Agreement signed for Purdue ID: testpuid').exists())


    def test_agreement_confirm_view_post_cancel(self):
        """Test POST to agreement_confirm with cancel button."""
        session = self.client.session
        session['purdue_id'] = 'testpuid_cancel'
        session.save()
        
        response = self.client.post(reverse('agreement_app:agreement_confirm'), {'cancel': '1'})
        self.assertRedirects(response, reverse('agreement_app:agreement_index'))
        # Ensure purdue_id is still in session if cancel is hit (or decide if it should be cleared)
        # For now, assuming it's not cleared on cancel, but this could be a design choice.
        # self.assertEqual(self.client.session.get('purdue_id'), 'testpuid_cancel')


    def test_agreement_success_view_get(self):
        """Test GET request to agreement_success view."""
        response = self.client.get(reverse('agreement_app:agreement_success'))
        self.assertEqual(response.status_code, 200)
        self.assertTemplateUsed(response, 'agreement_app/agreement_success.html')

class AdminFeatureViewTests(TestCase):
    def setUp(self):
        self.client = Client()
        self.staff_user = User.objects.create_user(username='staffuser', password='password', is_staff=True)
        self.normal_user = User.objects.create_user(username='normaluser', password='password')
        # Create a superuser for admin login if not already existing
        self.admin_user, created = User.objects.get_or_create(username='admin', defaults={'email': 'admin@example.com', 'is_staff': True, 'is_superuser': True})
        if created or not self.admin_user.check_password('password123'):
            self.admin_user.set_password('password123')
            self.admin_user.save()


    def test_download_checkin_logs_csv_staff(self):
        """Test GET download_checkin_logs_csv as staff user."""
        self.client.login(username='staffuser', password='password')
        CheckinLog.objects.create(purdue_id="test001", user_group="Student")
        CheckinLog.objects.create(purdue_id="test002", user_group="Faculty", visit_count=5)
        
        response = self.client.get(reverse('agreement_app:download_checkin_logs_csv'))
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response['Content-Type'], 'text/csv')
        self.assertTrue(response['Content-Disposition'].startswith('attachment; filename="checkin_logs_'))
        
        content = response.content.decode('utf-8')
        reader = csv.reader(StringIO(content))
        rows = list(reader)
        self.assertEqual(rows[0], ['Purdue ID', 'Timestamp', 'User Group', 'Visit Count'])
        self.assertTrue(any('test001' in row for row in rows[1:]))
        self.assertTrue(any('test002' in row for row in rows[1:]))
        self.assertTrue(any('5' in row for row in rows[1:] if 'test002' in row)) # Check visit_count
        self.assertTrue(DebugLogEntry.objects.filter(message=f'Check-in logs downloaded by {self.staff_user.username}').exists())

    def test_download_checkin_logs_csv_non_staff(self):
        """Test GET download_checkin_logs_csv as non-staff user."""
        self.client.login(username='normaluser', password='password')
        response = self.client.get(reverse('agreement_app:download_checkin_logs_csv'))
        # For staff_member_required, the default login URL is settings.LOGIN_URL
        # which we set to 'login' (resolved to 'accounts/login/')
        # If LOGIN_URL is not set, it defaults to '/accounts/login/'
        login_url = reverse('login') # Uses the name 'login' from django.contrib.auth.urls
        expected_redirect_url = f"{login_url}?next={reverse('agreement_app:download_checkin_logs_csv')}"
        self.assertRedirects(response, expected_redirect_url)


    def test_download_checkin_logs_csv_anonymous(self):
        """Test GET download_checkin_logs_csv as anonymous user."""
        response = self.client.get(reverse('agreement_app:download_checkin_logs_csv'))
        login_url = reverse('login')
        expected_redirect_url = f"{login_url}?next={reverse('agreement_app:download_checkin_logs_csv')}"
        self.assertRedirects(response, expected_redirect_url)

    def test_admin_reports_view_staff(self):
        """Test GET admin_reports as staff user."""
        self.client.login(username='staffuser', password='password')
        CheckinLog.objects.create(purdue_id='reportuser1', user_group='Student', timestamp=timezone.now())
        response = self.client.get(reverse('agreement_app:admin_reports'))
        self.assertEqual(response.status_code, 200)
        self.assertTemplateUsed(response, 'agreement_app/admin_reports.html')
        self.assertIn('usage_report', response.context)
        self.assertIn('chart_data_json', response.context)
        self.assertIn('calendar_data_json', response.context)

    def test_admin_reports_view_non_staff(self):
        """Test GET admin_reports as non-staff user."""
        self.client.login(username='normaluser', password='password')
        response = self.client.get(reverse('agreement_app:admin_reports'))
        login_url = reverse('login')
        expected_redirect_url = f"{login_url}?next={reverse('agreement_app:admin_reports')}"
        self.assertRedirects(response, expected_redirect_url)

    def test_session_keep_alive_view_authenticated(self):
        """Test GET session_keep_alive as authenticated user."""
        self.client.login(username='normaluser', password='password')
        response = self.client.get(reverse('agreement_app:session_keep_alive'))
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response['content-type'], 'application/json')
        json_response = json.loads(response.content) # Ensure json is imported
        self.assertEqual(json_response['status'], 'session_extended')
        self.assertTrue('timestamp' in json_response)

    def test_session_keep_alive_view_anonymous(self):
        """Test GET session_keep_alive as anonymous user."""
        response = self.client.get(reverse('agreement_app:session_keep_alive'))
        login_url = reverse('login')
        expected_redirect_url = f"{login_url}?next={reverse('agreement_app:session_keep_alive')}"
        self.assertRedirects(response, expected_redirect_url)
