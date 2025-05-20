# equipment_agreement
Demonstrate how to use Alma REST APIs, specifically the User API, to update user data. We accomplish this with a simple web form.

# Requirements
- PHP 7.0+ with both libxml and libcurl extensions enabled
- Python 3.x with requests module installed
- Alma API key that allows read/write operations on Users in the production environment

# Getting started
Update the domain in lines 8 and 123. Also, you'll want to set your Alma API key, which is line 34.

# Scripts
The repository contains several Python scripts for managing user agreement notes in Alma:

## fix_agreements.py
Adds agreement notes to users who have checked in equipment:
```bash
# Preview changes
python3 fix_agreements.py --dry-run

# Apply changes
python3 fix_agreements.py
```

## validate_note_segments.py
Ensures all agreement notes are in the "Internal" segment:
```bash
# Preview changes
python3 validate_note_segments.py --dry-run

# Apply changes
python3 validate_note_segments.py
```

The script will:
1. Find users with existing agreement notes
2. Check if the note is in the correct segment
3. Update notes that are not in the "Internal" segment
4. Provide a summary of changes made

# Note Structure
Agreement notes are added with the following properties:
- Segment Type: Internal
- Note Type: CIRCULATION
- Note Text: "Agreed to Knowledge Lab User Agreement"
- User Viewable: true
- Popup Note: true
