<?php

namespace App\Modules\Administration\Enums;

/**
 * Stable technical permission keys checked in code (Gates, Policies, route middleware).
 * Which permissions a given role holds is business data seeded by RolesAndPermissionsSeeder
 * and administrable later through the settings.manage UI — never hardcode a role name check
 * in application code, always gate on a Permission case.
 */
enum Permission: string
{
    // Facturation (devis, factures, avoirs, règlements, catalogue)
    case InvoicesView = 'invoices.view';
    case InvoicesCreate = 'invoices.create';
    case InvoicesUpdateDraft = 'invoices.update_draft';
    case InvoicesValidate = 'invoices.validate';
    case InvoicesCancel = 'invoices.cancel';
    case QuotesView = 'quotes.view';
    case QuotesCreate = 'quotes.create';
    case QuotesConvert = 'quotes.convert';
    case CreditNotesCreate = 'credit_notes.create';
    case PaymentsRecord = 'payments.record';
    case PaymentsReverse = 'payments.reverse';
    case CatalogManage = 'catalog.manage';

    // Tiers
    case PartiesManage = 'parties.manage';

    // Comptabilité générale
    case AccountingView = 'accounting.view';
    case AccountingEntriesCreate = 'accounting.entries.create';
    case AccountingEntriesPost = 'accounting.entries.post';
    case AccountingPeriodsClose = 'accounting.periods.close';

    // Trésorerie
    case TreasuryManage = 'treasury.manage';

    // Immobilisations
    case FixedAssetsManage = 'fixed_assets.manage';

    // États financiers
    case FinancialStatementsView = 'financial_statements.view';

    // Transversal
    case ReportsExport = 'reports.export';
    case SettingsManage = 'settings.manage';
    case UsersManage = 'users.manage';
    case AuditView = 'audit.view';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
