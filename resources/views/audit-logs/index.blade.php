<x-app-layout>
    <x-slot name="header"><div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-sm font-medium text-indigo-600">Contrôle interne</p><h1 class="text-2xl font-semibold text-gray-900">Journal d’audit</h1></div>@can(\App\Modules\Administration\Enums\Permission::ReportsExport->value)<a href="{{ route('exports.audit') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700">Exporter CSV</a>@endcan</div></x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">Le journal est en lecture seule. Il enregistre les opérations réussies sans conserver les mots de passe ni le contenu sensible des formulaires.</div>
        <form method="GET" class="mb-6 grid gap-3 rounded-xl bg-white p-4 ring-1 ring-gray-200 md:grid-cols-3 xl:grid-cols-[1fr_180px_220px_150px_150px_auto]">
            <input name="search" value="{{ $search }}" placeholder="Description ou route" class="rounded-md border-gray-300">
            <select name="action" class="rounded-md border-gray-300"><option value="">Toutes les actions</option>@foreach($actions as $item)<option value="{{ $item }}" @selected($action===$item)>{{ ucfirst($item) }}</option>@endforeach</select>
            <select name="user_id" class="rounded-md border-gray-300"><option value="">Tous les utilisateurs</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected($userId===$user->id)>{{ $user->name }}</option>@endforeach</select>
            <input name="from" type="date" value="{{ $from }}" class="rounded-md border-gray-300">
            <input name="to" type="date" value="{{ $to }}" class="rounded-md border-gray-300">
            <x-secondary-button type="submit">Filtrer</x-secondary-button>
        </form>
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-200"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">Utilisateur</th><th class="px-5 py-3">Action</th><th class="px-5 py-3">Description</th><th class="px-5 py-3">Objet</th><th class="px-5 py-3">Adresse IP</th></tr></thead>
            <tbody class="divide-y divide-gray-100">@forelse($logs as $log)<tr><td class="px-5 py-4 whitespace-nowrap text-sm"><a href="{{ route('audit-logs.show',$log) }}" class="font-semibold text-indigo-600">{{ $log->created_at->format('d/m/Y H:i:s') }}</a></td><td class="px-5 py-4 text-sm">{{ $log->user?->name ?? 'Utilisateur supprimé' }}</td><td class="px-5 py-4"><span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold">{{ ucfirst($log->action) }}</span></td><td class="px-5 py-4 text-sm">{{ $log->description }}</td><td class="px-5 py-4 text-sm">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td><td class="px-5 py-4 text-sm text-gray-500">{{ $log->ip_address ?? '—' }}</td></tr>@empty<tr><td colspan="6" class="px-6 py-14 text-center text-gray-500">Aucune opération auditée.</td></tr>@endforelse</tbody>
        </table></div>@if($logs->hasPages())<div class="border-t px-6 py-4">{{ $logs->links() }}</div>@endif</div>
    </div></div>
</x-app-layout>
