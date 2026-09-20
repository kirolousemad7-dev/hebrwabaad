# Project Workspace

Enhanced Project Workspace built on the existing Project / Task / Calendar / Files / Activity architecture.

## Client file policy (current)

**All project-scoped ManagedFile records are customer-readable** when the customer owns the project (or related order), by `ManagedFilePolicy` / `FileService::scopeVisibleTo`. There is **no** `is_client_visible` column on files. Do not invent a visibility field without an explicit product decision.

Manual browser checklist: [PROJECT_WORKSPACE_MANUAL_QA.md](./PROJECT_WORKSPACE_MANUAL_QA.md)

## Phase 3 (operations hardening)

- In-workspace task **quick edit** drawer (`PUT …/tasks/{task}`)
- Calendar link from workspace (`POST …/tasks/{task}/link-calendar`) — **idempotent** via `TaskCalendarLinkService`
- Due-date validation (start ≤ due) on create/edit
- Milestone progress includes **overdue task count** + `is_overdue`
- Workspace `risks` + `next_deadline` from existing health/progress data
- FileLibrary can attach uploads to existing project tasks (`task_id` already on ManagedFile)
- Customer sanitization feature tests

## Phase 2 (operational UX)

- Project header, tree + visual timeline, in-project task create, team stats, customer progress UI

Owner identity remains RBAC (`UserRole::Owner`) + `account_manager_id` — no `owner_id` column.

## Routes

- Owner UI: `/owner/projects/:id`
- Customer UI: `/dashboard/projects/:id`
- Ops: `/api/operations/projects/{project}/tasks` (+ update + link-calendar), structure, brief, phases, references, milestones
