<div class="sign-box">
    <div class="label">Pour {{ $company->name }}</div>
    @if ($company->signature_path)<img src="{{ $asset('signature') }}" alt="Signature">@endif
    @if ($company->stamp_path)<img src="{{ $asset('stamp') }}" alt="Cachet">@endif
    @if ($company->representative_name)<div><span class="strong">{{ $company->representative_name }}</span>@if ($company->representative_title) — {{ $company->representative_title }}@endif</div>@endif
</div>
