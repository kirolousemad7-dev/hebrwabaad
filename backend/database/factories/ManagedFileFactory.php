<?php

namespace Database\Factories;

use App\Models\ManagedFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ManagedFile>
 */
class ManagedFileFactory extends Factory
{
    protected $model = ManagedFile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stored = Str::uuid()->toString().'.pdf';

        return [
            'uploaded_by' => User::factory(),
            'original_name' => 'brief.pdf',
            'stored_name' => $stored,
            'disk' => 'local',
            'path' => 'files/'.$stored,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 2048,
        ];
    }
}
