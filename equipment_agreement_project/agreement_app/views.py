from django.shortcuts import render, redirect
from django.urls import reverse
from .forms import PurdueIdForm
from .models import CheckinLog, DebugLogEntry # Import models
import csv
from django.http import HttpResponse, JsonResponse # Added JsonResponse
from django.contrib.admin.views.decorators import staff_member_required
from django.contrib.auth.decorators import login_required # Added login_required
from datetime import datetime, date
import calendar
import json
from collections import defaultdict
import time # Added time

def index(request):
    if request.method == 'POST':
        form = PurdueIdForm(request.POST)
        if form.is_valid():
            request.session['purdue_id'] = form.cleaned_data['purdue_id']
            # Use namespaced URL
            return redirect('agreement_app:agreement_confirm')
    else:
        form = PurdueIdForm()
    return render(request, 'agreement_app/index.html', {'form': form})

def agreement_confirm_view(request):
    purdue_id = request.session.get('purdue_id')
    if not purdue_id:
        # Use namespaced URL
        return redirect('agreement_app:agreement_index')

    # Placeholder for Alma GET
    user_data = {
        'first_name': 'Test',
        'last_name': 'User',
        'email': 'test@example.com',
        'phone': '123-456-7890',
        'user_group': 'Student',
        'agreement_exists': False,
        'agreement_date': None
    }
    print(f"Simulating Alma API GET for {purdue_id}. Returning dummy data.")

    if request.method == 'POST':
        if 'confirm_agreement' in request.POST:
            # Placeholder for Alma PUT
            print(f"Simulating Alma API PUT for {purdue_id} to add agreement.")
            
            # Placeholder for Email
            print(f"Simulating sending email to {user_data['email']}.")

            # Save to CheckinLog
            CheckinLog.objects.create(
                purdue_id=purdue_id,
                user_group=user_data.get('user_group') 
            )
            DebugLogEntry.objects.create(level='INFO', message=f'Agreement signed for Purdue ID: {purdue_id}')

            # Clear purdue_id from session
            if 'purdue_id' in request.session:
                del request.session['purdue_id']
            
            # Use namespaced URL
            return redirect('agreement_app:agreement_success')
        elif 'cancel' in request.POST: # Assuming a cancel button might also POST
             # Use namespaced URL
            return redirect('agreement_app:agreement_index')

    return render(request, 'agreement_app/agreement_confirm.html', {
        'purdue_id': purdue_id,
        'user_data': user_data
    })

def agreement_success_view(request):
    return render(request, 'agreement_app/agreement_success.html')

@staff_member_required
def download_checkin_logs_csv(request):
    response = HttpResponse(content_type='text/csv')
    response['Content-Disposition'] = f'attachment; filename="checkin_logs_{datetime.now().strftime("%Y-%m-%d")}.csv"'

    writer = csv.writer(response)
    writer.writerow(['Purdue ID', 'Timestamp', 'User Group', 'Visit Count'])

    logs = CheckinLog.objects.all().values_list('purdue_id', 'timestamp', 'user_group', 'visit_count')
    for log in logs:
        writer.writerow(log)

    DebugLogEntry.objects.create(level='INFO', message=f'Check-in logs downloaded by {request.user.username}')
    return response

@staff_member_required
def admin_reports_view(request):
    logs = CheckinLog.objects.all().order_by('timestamp')
    
    # Process data for Monthly Usage Report
    usage_report = defaultdict(lambda: defaultdict(int))
    unique_user_groups = set()
    
    for log in logs:
        month_year = log.timestamp.strftime('%Y-%m')
        user_group = log.user_group if log.user_group else "Unknown"
        usage_report[month_year][user_group] += 1
        unique_user_groups.add(user_group)
        
    # Sort user groups for consistent chart colors
    sorted_user_groups = sorted(list(unique_user_groups))

    # Process data for Chart.js
    labels = sorted(usage_report.keys())
    datasets = []
    
    # Define a list of colors for the chart
    colors = [
        'rgba(255, 99, 132, 0.2)', 'rgba(54, 162, 235, 0.2)', 'rgba(255, 206, 86, 0.2)',
        'rgba(75, 192, 192, 0.2)', 'rgba(153, 102, 255, 0.2)', 'rgba(255, 159, 64, 0.2)'
    ]
    border_colors = [
        'rgba(255, 99, 132, 1)', 'rgba(54, 162, 235, 1)', 'rgba(255, 206, 86, 1)',
        'rgba(75, 192, 192, 1)', 'rgba(153, 102, 255, 1)', 'rgba(255, 159, 64, 1)'
    ]

    for i, group in enumerate(sorted_user_groups):
        data = [usage_report[month].get(group, 0) for month in labels]
        datasets.append({
            'label': group,
            'data': data,
            'backgroundColor': colors[i % len(colors)],
            'borderColor': border_colors[i % len(border_colors)],
            'borderWidth': 1
        })
        
    chart_data = {
        'labels': labels,
        'datasets': datasets
    }
    chart_data_json = json.dumps(chart_data)

    # Process data for Calendar View (Current Month)
    today = date.today()
    current_month_str = today.strftime('%Y-%m')
    
    daily_data = defaultdict(lambda: {'total': 0, 'groups': defaultdict(int)})
    month_logs = logs.filter(timestamp__year=today.year, timestamp__month=today.month)
    
    for log in month_logs:
        day_str = log.timestamp.strftime('%Y-%m-%d')
        user_group = log.user_group if log.user_group else "Unknown"
        daily_data[day_str]['total'] += 1
        daily_data[day_str]['groups'][user_group] += 1
        
    calendar_data_json = json.dumps(dict(daily_data)) # Convert defaultdict to dict for JSON serialization

    # Prepare usage_report for template (convert defaultdict to regular dict)
    template_usage_report = {month: dict(counts) for month, counts in usage_report.items()}

    context = {
        'usage_report': template_usage_report,
        'chart_data_json': chart_data_json,
        'calendar_data_json': calendar_data_json,
        'current_month_year': today.strftime("%B %Y"), # For calendar display
    }
    return render(request, 'agreement_app/admin_reports.html', context)

@login_required
def session_keep_alive_view(request):
    request.session['last_activity'] = time.time()
    return JsonResponse({'status': 'session_extended', 'timestamp': request.session['last_activity']})
