from django import forms

class PurdueIdForm(forms.Form):
    purdue_id = forms.CharField(label='Purdue ID', max_length=100)
