<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;

/**
 * Catalog resources are shared between the public catalog and the
 * owner/admin management API. Management-only fields are added for
 * users who are allowed to manage the catalog.
 */
class CatalogVisibility
{
    /**
     * @param  callable(): array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public static function managementFields(Request $request, callable $fields): array
    {
        $role = $request->user()?->role;

        if ($role instanceof UserRole && $role->canManageCatalog()) {
            return $fields();
        }

        return [];
    }
}
