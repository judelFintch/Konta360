<div class="sign-box">
    <div class="label">{{ __('document.for_company', ['company' => $company->name], $locale ?? 'fr') }}</div>
    @if ($size = $company->brandImageSize('signature', 110, 56))<img src="{{ $asset('signature') }}" alt="Signature" style="width: {{ $size[0] }}px; height: {{ $size[1] }}px">@endif
    @if ($size = $company->brandImageSize('stamp', 110, 56))<img src="{{ $asset('stamp') }}" alt="Cachet" style="width: {{ $size[0] }}px; height: {{ $size[1] }}px">@endif
    @if ($company->representative_name)<div><span class="strong">{{ $company->representative_name }}</span>@if ($company->representative_title) — {{ $company->representative_title }}@endif</div>@endif
</div>
