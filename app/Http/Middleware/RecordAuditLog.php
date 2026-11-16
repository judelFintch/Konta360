<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Modules\Companies\Services\CurrentCompany;
use App\Modules\Companies\Services\PlatformCompanyAccess;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordAuditLog
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user() || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        // The audit trail belongs to a company; platform requests have none.
        if (! app(CurrentCompany::class)->has()) {
            return $response;
        }

        if ($response->getStatusCode() >= 400 || $request->session()->has('errors')) {
            return $response;
        }

        $route = $request->route();
        $routeName = $route?->getName();
        $subject = collect($route?->parameters() ?? [])->first(fn ($parameter) => $parameter instanceof Model);
        $metadata = [
            'route_parameters' => collect($route?->parameters() ?? [])
                ->map(fn ($value) => $value instanceof Model ? $value->getKey() : $value)
                ->all(),
        ];

        if (app(PlatformCompanyAccess::class)->isActiveFor($request->user())) {
            $metadata['platform_support_access'] = true;
            $metadata['platform_actor_id'] = $request->user()->id;
        }
        if ($routeName === 'invoices.deduction-correction') {
            $metadata['invoice_deduction_correction_id'] = $request->attributes->get('invoice_deduction_correction_id');
            $metadata['reason'] = $request->input('reason');
        }
        if ($routeName === 'invoices.label-correction') {
            $metadata['invoice_label_correction_id'] = $request->attributes->get('invoice_label_correction_id');
            $metadata['reason'] = $request->input('label_correction_reason');
        }
        if ($routeName === 'invoices.tax-correction') {
            $metadata['invoice_tax_correction_id'] = $request->attributes->get('invoice_tax_correction_id');
            $metadata['tax_rates'] = $request->input('tax_rates');
            $metadata['reason'] = $request->input('reason');
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $this->action($request),
            'route_name' => $routeName,
            'http_method' => $request->method(),
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => $this->description($routeName),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);

        return $response;
    }

    private function action(Request $request): string
    {
        $route = (string) $request->route()?->getName();

        return match (true) {
            str_contains($route, '-correction') => 'correction',
            str_contains($route, 'validate'), str_contains($route, '.post') => 'validation',
            str_contains($route, 'cancel'), str_contains($route, 'reverse') => 'annulation',
            str_contains($route, 'pay'), str_contains($route, 'payments') => 'paiement',
            str_contains($route, 'close') => 'cloture',
            str_contains($route, 'assign') => 'affectation',
            $request->isMethod('delete') => 'suppression',
            $request->isMethod('post') => 'creation',
            default => 'modification',
        };
    }

    private function description(?string $routeName): string
    {
        return match ($routeName) {
            'invoices.deduction-correction' => 'Correction des frais et avances d’une facture validée',
            'invoices.label-correction' => 'Correction des libellés d’une facture validée',
            'invoices.tax-correction' => 'Correction de la TVA d’une facture validée',
            'invoices.validate' => 'Validation d’une facture',
            'invoices.cancel' => 'Annulation d’une facture',
            'payments.store' => 'Enregistrement d’un règlement client',
            'payments.reverse' => 'Annulation d’un règlement client',
            'expenses.validate' => 'Validation d’une dépense',
            'expenses.pay' => 'Paiement d’une dépense fournisseur',
            'accounting.entries.post' => 'Comptabilisation d’une écriture',
            'accounting.periods.close' => 'Clôture d’une période comptable',
            'treasury.transactions.store' => 'Comptabilisation d’un mouvement de trésorerie',
            'treasury.reconciliations.store' => 'Validation d’un rapprochement bancaire',
            default => 'Opération '.($routeName ?: 'applicative'),
        };
    }
}
