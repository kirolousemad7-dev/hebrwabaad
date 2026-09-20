# Project Workspace — Manual Browser QA Checklist

Use this checklist for authenticated production verification. Mark each item only after executing it.

**Environment:** ________________  
**Tester:** ________________  
**Date:** ________________  

Status values: `PASS` | `FAIL` | `BLOCKED` | `N/A`

---

## Owner — `/owner/projects/{id}`

| # | Step | Status | Notes |
|---|------|--------|-------|
| 1 | Login as Owner | | |
| 2 | Open project workspace | | |
| 3 | Create task (title + assignee) | | |
| 4 | Assign / change assignee | | |
| 5 | Edit task (status, dates, milestone, visibility) | | |
| 6 | Link task to calendar | | |
| 7 | Verify single calendar item (no duplicate on re-link) | | |
| 8 | Complete task | | |
| 9 | Verify milestone progress / open_tasks | | |
| 10 | Verify execution summary numbers | | |
| 11 | Verify attention panel (overdue / due soon / waiting) | | |
| 12 | Verify closure readiness state | | |
| 13 | Open files tab / upload | | |
| 14 | Open deliverables section | | |
| 15 | Open approvals (if any pending) | | |

---

## Account Manager

| # | Step | Status | Notes |
|---|------|--------|-------|
| 1 | Login as assigned AM | | |
| 2 | Open assigned project | | |
| 3 | Create / update task (allowed ops) | | |
| 4 | Open unauthorized project (other AM) | | |
| 5 | Confirm access blocked (403 / error UI) | | |

---

## Customer — `/dashboard/projects/{id}`

| # | Step | Status | Notes |
|---|------|--------|-------|
| 1 | Login as project customer | | |
| 2 | Open customer project detail | | |
| 3 | Verify progress (client-visible tasks only) | | |
| 4 | Verify client-visible milestones / phases | | |
| 5 | Verify deliverables / service progress | | |
| 6 | Verify client-safe references / files | | |
| 7 | Confirm no internal ops panels (team, health, attention, activity) | | |
| 8 | Confirm no staff-only actions (task create/edit, approvals) | | |

---

## Regression smoke (optional)

| Area | Load OK? | Notes |
|------|----------|-------|
| Calendar | | |
| CRM | | |
| Orders | | |
| Printing Operations | | |
| Owner Workspace home | | |
| Customer Portal home | | |

---

## Sign-off

Overall: `PASS` / `FAIL` / `BLOCKED`  

Signature: ________________
