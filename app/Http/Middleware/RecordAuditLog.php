<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
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

        if ($response->getStatusCode() >= 400 || $request->session()->has('errors')) {
            return $response;
        }

        $route = $request->route();
        $routeName = $route?->getName();
        $subject = collect($route?->parameters() ?? [])->first(fn ($parameter) => $parameter instanceof Model);

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
            'metadata' => [
                'route_parameters' => collect($route?->parameters() ?? [])
                    ->map(fn ($value) => $value instanceof Model ? $value->getKey() : $value)
                    ->all(),
            ],
        ]);

        return $response;
    }

    private function action(Request $request): string
    {
        $route = (string) $request->route()?->getName();

        return match (true) {
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
