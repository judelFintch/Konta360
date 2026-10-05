<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Modules\Administration\Enums\Permission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAudit();
        $search = trim((string) $request->query('search'));
        $action = $request->query('action');
        $userId = $request->integer('user_id') ?: null;
        $from = $request->query('from');
        $to = $request->query('to');

        $logs = AuditLog::query()->with('user')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('description', 'like', "%{$search}%")
                    ->orWhere('route_name', 'like', "%{$search}%");
            }))
            ->when($action, fn ($query) => $query->where('action', $action))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->latest('id')->paginate(30)->withQueryString();
        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');
        $users = User::query()->whereBelongsTo(Company::current())->whereHas('auditLogs')->orderBy('name')->get();

        return view('audit-logs.index', compact('logs', 'actions', 'users', 'search', 'action', 'userId', 'from', 'to'));
    }

    public function show(AuditLog $auditLog): View
    {
        $this->authorizeAudit();
        $auditLog->load('user');

        return view('audit-logs.show', compact('auditLog'));
    }

    private function authorizeAudit(): void
    {
        abort_unless(auth()->user()->can(Permission::AuditView->value), 403);
    }
}
