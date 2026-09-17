<?php

namespace App\Enums;

/**
 * Capability strings for supplier management.
 * Mapped onto existing UserRole gates — not a separate ACL package.
 */
enum SupplierPermission: string
{
    case View = 'suppliers.view';
    case Create = 'suppliers.create';
    case Update = 'suppliers.update';
    case Delete = 'suppliers.delete';
    case Approve = 'suppliers.approve';
    case Suspend = 'suppliers.suspend';
    case Verify = 'suppliers.verify';
    case ViewInternal = 'suppliers.view_internal';
    case ManageServices = 'suppliers.manage_services';
    case ManageProducts = 'suppliers.manage_products';
    case ManagePortfolio = 'suppliers.manage_portfolio';
    case ManageDocuments = 'suppliers.manage_documents';
    case ManagePricing = 'suppliers.manage_pricing';

    /**
     * @return list<self>
     */
    public static function catalogManagerPermissions(): array
    {
        return self::cases();
    }

    public function grantedTo(UserRole $role): bool
    {
        if ($role->canManageCatalog()) {
            return true;
        }

        if ($role === UserRole::Supplier) {
            return in_array($this, [
                self::View,
                self::Update,
                self::ManageServices,
                self::ManageProducts,
                self::ManagePortfolio,
                self::ManageDocuments,
                self::ManagePricing,
            ], true);
        }

        return false;
    }
}
