# Graph Report - .  (2026-09-18)

## Corpus Check
- Corpus is ~25,081 words - fits in a single context window. You may not need a graph.

## Summary
- 97 nodes · 123 edges · 18 communities (17 shown, 1 thin omitted)
- Extraction: 92% EXTRACTED · 8% INFERRED · 0% AMBIGUOUS · INFERRED: 10 edges (avg confidence: 0.78)
- Token cost: 1,250 input · 650 output

## Community Hubs (Navigation)
- Admin Operations & Log Management
- Admin Operations & Log Management
- Patron Agreement & Alma REST API
- Authentication & Access Control
- Patron Agreement & Alma REST API
- Kiosk Session Persistence
- Authentication & Access Control
- Admin Operations & Log Management
- Admin Operations & Log Management
- Admin Operations & Log Management
- Patron Agreement & Alma REST API

## God Nodes (most connected - your core abstractions)
1. `Logger` - 8 edges
2. `process_debug_log()` - 7 edges
3. `parseLogLine()` - 5 edges
4. `process_users()` - 5 edges
5. `getLoggingStatus()` - 5 edges
6. `setupVisibilityHandlers()` - 5 edges
7. `process_users()` - 5 edges
8. `main()` - 4 edges
9. `refreshSession()` - 4 edges
10. `main()` - 4 edges

## Surprising Connections (you probably didn't know these)
- `Cline Project Documentation` --semantically_similar_to--> `README Documentation`  [INFERRED] [semantically similar]
  cline_docs/codebaseSummary.md → README.md
- `Architectural Memory Bank` --semantically_similar_to--> `README Documentation`  [INFERRED] [semantically similar]
  memory-bank/systemPatterns.md → README.md
- `Super Admin User Manager` --conceptually_related_to--> `Role-Based Access Control (RBAC)`  [INFERRED]
  README.md → allowed_users.txt
- `Super Admin User Manager` --references--> `Allowed Users ACL`  [EXTRACTED]
  README.md → allowed_users.txt

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Authentication & Authorization Subsystem** — purdue_saml_sso, role_based_access_control, superadmin_user_manager, allowed_users [INFERRED 0.95]

## Communities (18 total, 1 thin omitted)

### Community 0 - "Admin Operations & Log Management"
Cohesion: 0.19
Nodes (10): getCheckinLogEntries(), getDailyUsageData(), getDepartmentUsageReport(), getUsageReport(), parseLogLine(), processLogForDailyData(), processLogForDeptUsage(), processLogForUsageReport() (+2 more)

### Community 1 - "Admin Operations & Log Management"
Cohesion: 0.23
Nodes (6): debugLog(), getLoggingStatus(), Logger, posix_getgrgid(), posix_getpwuid(), Purdue University Libraries Logo

### Community 2 - "Patron Agreement & Alma REST API"
Cohesion: 0.24
Nodes (11): add_agreement_note(), check_user_agreement(), get_unique_users(), main(), parse_php_config(), process_users(), Process users and add agreement notes where missing., Parse PHP config file for API key and settings. (+3 more)

### Community 3 - "Authentication & Access Control"
Cohesion: 0.24
Nodes (11): extract_purdue_id(), extract_user_group(), get_visit_count(), main(), parse_timestamp(), process_debug_log(), Extract Purdue ID from API call or PUT request log line., Extract user group from XML response. (+3 more)

### Community 4 - "Patron Agreement & Alma REST API"
Cohesion: 0.24
Nodes (11): check_user_agreement(), fix_note_segment(), get_unique_users(), main(), parse_php_config(), process_users(), Process users and fix agreement note segments where needed., Parse PHP config file for API key and settings. (+3 more)

### Community 5 - "Kiosk Session Persistence"
Cohesion: 0.73
Nodes (5): handleSleep(), handleWake(), initSessionMonitor(), refreshSession(), setupVisibilityHandlers()

### Community 6 - "Authentication & Access Control"
Cohesion: 0.40
Nodes (5): Cline Project Documentation, 12-Hour Kiosk Session Persistence, Architectural Memory Bank, Purdue SAML SSO Auth Hub, README Documentation

### Community 7 - "Admin Operations & Log Management"
Cohesion: 0.83
Nodes (3): extract_id_from_debug(), main(), normalize_id()

### Community 8 - "Admin Operations & Log Management"
Cohesion: 1.00
Nodes (3): debugLog(), pushUserNoteAndCheckAgreement(), sendAgreementEmail()

### Community 9 - "Admin Operations & Log Management"
Cohesion: 1.00
Nodes (3): Allowed Users ACL, Role-Based Access Control (RBAC), Super Admin User Manager

## Knowledge Gaps
- **6 isolated node(s):** `12-Hour Kiosk Session Persistence`, `Alma User REST API Integration`, `Log Rotation & Disaster Recovery`, `Purdue University Libraries Logo`, `Architectural Memory Bank` (+1 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **1 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `Role-Based Access Control (RBAC)` connect `Admin Operations & Log Management` to `Admin Operations & Log Management`?**
  _High betweenness centrality (0.007) - this node is a cross-community bridge._
- **What connects `12-Hour Kiosk Session Persistence`, `Alma User REST API Integration`, `Log Rotation & Disaster Recovery` to the rest of the system?**
  _6 weakly-connected nodes found - possible documentation gaps or missing edges._