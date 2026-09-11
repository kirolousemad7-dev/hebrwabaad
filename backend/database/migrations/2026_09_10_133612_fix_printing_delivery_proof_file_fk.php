<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('printing_deliveries') || ! Schema::hasColumn('printing_deliveries', 'proof_file_id')) {
            return;
        }

        if (! Schema::hasTable('files')) {
            return;
        }

        // Fresh installs already constrain proof_file_id → files via phase8 migration.
        if ($this->proofFileReferencesFiles()) {
            return;
        }

        Schema::table('printing_deliveries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('proof_file_id');
        });

        Schema::table('printing_deliveries', function (Blueprint $table): void {
            $table->foreignId('proof_file_id')
                ->nullable()
                ->after('notes')
                ->constrained('files')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Intentionally empty — do not restore the incorrect managed_files FK.
    }

    private function proofFileReferencesFiles(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $row = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'printing_deliveries'");
            $sql = is_object($row) ? (string) ($row->sql ?? '') : '';

            return str_contains(strtolower($sql), 'references "files"')
                || str_contains(strtolower($sql), 'references files');
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $database = Schema::getConnection()->getDatabaseName();
            $count = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', $database)
                ->where('TABLE_NAME', 'printing_deliveries')
                ->where('COLUMN_NAME', 'proof_file_id')
                ->where('REFERENCED_TABLE_NAME', 'files')
                ->count();

            return $count > 0;
        }

        return false;
    }
};
