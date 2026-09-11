<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Optional demo departments (Arabic labels). Not called from DatabaseSeeder.
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'التصميم', 'sort_order' => 1],
            ['name' => 'التطوير', 'sort_order' => 2],
            ['name' => 'التسويق', 'sort_order' => 3],
            ['name' => 'المبيعات', 'sort_order' => 4],
            ['name' => 'العمليات', 'sort_order' => 5],
        ];

        foreach ($rows as $row) {
            $slug = Str::slug($row['name']);
            if ($slug === '') {
                $slug = 'department-'.$row['sort_order'];
            }

            Department::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $row['name'],
                    'description' => null,
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                ],
            );
        }
    }
}
