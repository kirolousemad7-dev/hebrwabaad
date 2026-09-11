<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Unified Work list driver
    |--------------------------------------------------------------------------
    |
    | sql  — UNION ALL projection over tasks + calendar_items (masters only),
    |        then for buckets today|this_week|upcoming a bounded RecurrenceExpander
    |        merge (Phase 6K V2, cap 100 occurrences). Unconstrained all/completed
    |        lists stay persistent-only (no expansion).
    | php  — Bounded in-memory merge (Phase 4 fallback); also expands windowed
    |        recurrence the same way.
    |
    | When the SQL path fails, or filters request legacy expand_occurrences /
    | expand_recurrence, UnifiedWorkService falls back to php.
    |
    | Benchmark: php artisan operations:benchmark-unified-work
    | (optionally compare bucket=today after seeding recurring masters).
    |
    | Writable only via OPERATIONS_UNIFIED_WORK_DRIVER in .env (not the settings API).
    |
    */
    'unified_work_driver' => env('OPERATIONS_UNIFIED_WORK_DRIVER', 'sql'),

    /*
    |--------------------------------------------------------------------------
    | Delivery / shipping providers
    |--------------------------------------------------------------------------
    |
    | Only providers listed here (and implemented) appear as available.
    | No DHL/Aramex until contracted and configured.
    |
    */
    'delivery_providers' => [
        'available' => ['manual'],
    ],

];
