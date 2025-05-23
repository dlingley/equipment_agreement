from django.db import models

class CheckinLog(models.Model):
    purdue_id = models.CharField(max_length=100)
    timestamp = models.DateTimeField(auto_now_add=True)
    user_group = models.CharField(max_length=100, blank=True, null=True)
    visit_count = models.IntegerField(blank=True, null=True)

    def __str__(self):
        return f"{self.purdue_id} - {self.timestamp.strftime('%Y-%m-%d %H:%M:%S')}"

class DebugLogEntry(models.Model):
    timestamp = models.DateTimeField(auto_now_add=True)
    level = models.CharField(max_length=50)  # e.g., INFO, ERROR, DEBUG
    message = models.TextField()

    def __str__(self):
        return f"{self.timestamp.strftime('%Y-%m-%d %H:%M:%S')} [{self.level}]"
