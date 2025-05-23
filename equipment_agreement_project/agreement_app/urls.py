from django.urls import path
from . import views

app_name = 'agreement_app'

urlpatterns = [
    path('', views.index, name='agreement_index'),
    path('confirm/', views.agreement_confirm_view, name='agreement_confirm'),
    path('success/', views.agreement_success_view, name='agreement_success'),
    path('download_logs/', views.download_checkin_logs_csv, name='download_checkin_logs_csv'),
    path('admin_reports/', views.admin_reports_view, name='admin_reports'),
    path('keepalive/', views.session_keep_alive_view, name='session_keep_alive'),
]
