{{-- $signatories: list of [label, name|null] --}}
<table class="signatures">
    <tr>
        @foreach ($signatories as [$label, $name])
            <td>
                <div class="sign-box">
                    <div class="sign-label">{{ $label }}</div>
                    <div class="muted">Nom : {{ $name ?? '' }}</div>
                    <div class="muted">Date et signature :</div>
                </div>
            </td>
        @endforeach
    </tr>
</table>
