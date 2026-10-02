{{-- Statut de la facture puis, si elle est validée, sa situation de paiement. --}}
@php use App\Modules\Invoices\Enums\InvoiceStatus; @endphp
@if ($invoice->status === InvoiceStatus::Draft)
    <x-badge color="gray">Brouillon</x-badge>
@elseif ($invoice->status === InvoiceStatus::Cancelled)
    <x-badge color="red">Annulée</x-badge>
@elseif ($invoice->balanceDue() <= 0)
    <x-badge color="green">Payée</x-badge>
@elseif ($invoice->isOverdue())
    <x-badge color="red">En retard</x-badge>
@elseif ($invoice->paidAmount() > 0)
    <x-badge color="amber">Partiellement payée</x-badge>
@else
    <x-badge color="indigo">À encaisser</x-badge>
@endif
