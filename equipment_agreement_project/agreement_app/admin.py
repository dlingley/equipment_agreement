from django.contrib import admin
from .models import CheckinLog, DebugLogEntry

class CheckinLogAdmin(admin.ModelAdmin):
    list_display = ('purdue_id', 'timestamp', 'user_group', 'visit_count')
    list_filter = ('timestamp', 'user_group')
    search_fields = ('purdue_id', 'user_group')

class DebugLogEntryAdmin(admin.ModelAdmin):
    list_display = ('timestamp', 'level', 'message_summary')
    list_filter = ('timestamp', 'level')
    search_fields = ('level', 'message')

    def message_summary(self, obj):
        return obj.message[:100] + "..." if len(obj.message) > 100 else obj.message
    message_summary.short_description = 'Message (Summary)'

admin.site.register(CheckinLog, CheckinLogAdmin)
admin.site.register(DebugLogEntry, DebugLogEntryAdmin)
