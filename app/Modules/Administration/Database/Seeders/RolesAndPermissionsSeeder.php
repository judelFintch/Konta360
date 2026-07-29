<?php

namespace App\Modules\Administration\Database\Seeders;

use App\Modules\Administration\Enums\Permission as PermissionEnum;
use App\Modules\Administration\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the technical permission catalogue (from the Permission enum) and the four
 * roles defined by the cahier des charges, with a default permission matrix per role.
 *
 * The exact permission set assigned to each role below is a provisional interpretation
 * of the cahier des charges role descriptions (see docs/adr/0001-roles-and-permissions.md)
 * and must be validated by the client before production use, like the rest of the
 * business rules that aren't backed by an authoritative source document yet.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionEnum::values() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // DatabaseSeeder runs seeders inside Model::withoutEvents() (via WithoutModelEvents),
        // which silently disables the "saved" listener spatie/laravel-permission relies on to
        // invalidate its permission cache. Without this, the permissions just created above
        // stay invisible to Role::syncPermissions() below and it throws PermissionDoesNotExist.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $administrateur = Role::findOrCreate(RoleEnum::Administrateur->value, 'web');
        $administrateur->syncPermissions(PermissionEnum::values());

        $comptable = Role::findOrCreate(RoleEnum::Comptable->value, 'web');
        $comptable->syncPermissions([
            PermissionEnum::InvoicesView,
            PermissionEnum::InvoicesCreate,
            PermissionEnum::InvoicesUpdateDraft,
            PermissionEnum::InvoicesValidate,
            PermissionEnum::InvoicesCancel,
            PermissionEnum::QuotesView,
            PermissionEnum::QuotesCreate,
            PermissionEnum::QuotesConvert,
            PermissionEnum::CreditNotesCreate,
            PermissionEnum::PaymentsRecord,
            PermissionEnum::PaymentsReverse,
            PermissionEnum::CatalogManage,
            PermissionEnum::PartiesManage,
            PermissionEnum::AccountingView,
            PermissionEnum::AccountingEntriesCreate,
            PermissionEnum::AccountingEntriesPost,
            PermissionEnum::TreasuryManage,
            PermissionEnum::FixedAssetsManage,
            PermissionEnum::FinancialStatementsView,
            PermissionEnum::ReportsExport,
        ]);

        $direction = Role::findOrCreate(RoleEnum::Direction->value, 'web');
        $direction->syncPermissions([
            PermissionEnum::InvoicesView,
            PermissionEnum::QuotesView,
            PermissionEnum::AccountingView,
            PermissionEnum::AccountingEntriesPost,
            PermissionEnum::FinancialStatementsView,
            PermissionEnum::ReportsExport,
            PermissionEnum::AuditView,
        ]);

        $commercial = Role::findOrCreate(RoleEnum::Commercial->value, 'web');
        $commercial->syncPermissions([
            PermissionEnum::InvoicesView,
            PermissionEnum::InvoicesCreate,
            PermissionEnum::InvoicesUpdateDraft,
            PermissionEnum::QuotesView,
            PermissionEnum::QuotesCreate,
            PermissionEnum::QuotesConvert,
            PermissionEnum::PartiesManage,
        ]);
    }
}
