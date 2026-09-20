# Work Calendar — Production Operations

Internal runbook for Hebr & Ab3ad Work Calendar (Phase 1–2.5).

## Timezone strategy

- **Database / Laravel app timezone:** UTC (`config/app.php`).
- **API:** Timed events are ISO-8601 UTC instants.
- **All-day events:** Stored as `YYYY-MM-DDT00:00:00.000Z` (UTC midnight of the intended calendar date). Frontend must not convert all-day values through local `Date` in a way that shifts the calendar day.
- **Display:** Frontend localizes timed events for the browser locale; all-day grouping uses the UTC date portion (`Y-m-d`).

Do **not** switch DB timestamps to a local region timezone.

## Occurrence identity

Virtual recurrence rows in list responses use:

```text
{masterId}:{Y-m-d}
```

Example: `42:2026-09-15`

Mutations **must** target the real `calendar_items.id` (integer) plus optional `occurrence_at`.

Backend helper: `App\Support\Calendar\CalendarOccurrenceReference`

Route binding for `{calendarItem}` parses virtual ids and loads the real master row (never uses the string as a primary key).

## Recurrence

- Rules stored on the master row (`recurrence_rule`, `recurrence_until`, `recurrence_count`, `recurrence_exceptions`).
- Expansion is **on read** for the requested date range only (caps in `RecurrenceExpander`).
- Mutation scopes: `this` | `future` | `all`.
- `future` splits the series: old master ends before the split; new master continues from the split (history preserved).

Range grid API rejects spans **> 93 days** (422).

## Reminders & digests

Commands:

| Command | Schedule | Notes |
|---------|----------|-------|
| `calendar:dispatch-reminders` | every minute | `withoutOverlapping(5)`; claims via `sent_at` + `lockForUpdate` |
| `calendar:daily-digest` | daily 07:00 UTC | `withoutOverlapping`; only staff with `daily_digest` setting |

**Production requirement:** cron (or supervisor) must run:

```bash
php artisan schedule:run
```

every minute. Without this, reminders and digests will not fire.

Queues: Calendar notifications use the `database` channel synchronously unless the app later queues notifications. No Redis is required specifically for Calendar.

## Derived events

Read-only overlays from Projects, Printing (`required_date`), Orders (managers), CRM quotations (`valid_until`), plus existing CRM calendar merge.

Permissions are checked **per source** before serialization. Broken routes are avoided via `CalendarRelatedEntityUrlResolver` (null href → UI shows label without “فتح السجل”).

Payments/suppliers are not auto-derived when no real due/schedule field exists.

## ICS

- Timed: `DTSTART` / `DTEND` as UTC `Z`.
- All-day: `VALUE=DATE` using the UTC calendar date.
- Export endpoints respect the same visibility scope as list APIs.

## Workload levels

Central rules in `CalendarWorkloadRules`:

- light / medium / heavy from open-task score (+ urgent/overdue weight).
- Completed/cancelled excluded.
- Virtual recurrence expansions are **not** double-counted in workload (DB rows only).

## Troubleshooting

```bash
php artisan calendar:dispatch-reminders
php artisan calendar:daily-digest
php artisan schedule:list
php artisan test --filter=Calendar
```

Check soft-deleted items are excluded from reminders. Check `calendar_reminders.sent_at` for duplicate delivery investigations.

## Migration notes

Phase 2 additive migration: `2026_09_08_180000_extend_calendar_phase2_tables.php`.

Phase 1 rows remain valid with nullable recurrence/location/checklist columns.
