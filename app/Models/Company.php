<?php

namespace App\Models;

use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Companies\Scopes\CompanyScope;
use App\Modules\Companies\Services\CurrentCompany;
use Database\Factories\CompanyFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A subscribing organisation. Every business record belongs to exactly one
 * company and is only visible from inside that company (ADR 0002).
 */
#[Fillable([
    'name', 'legal_form', 'tax_identifier', 'trade_register', 'address', 'email',
    'phone', 'website', 'default_currency', 'invoice_footer', 'national_identifier',
    'cnss_number', 'representative_name', 'representative_title', 'bank_name',
    'bank_account_name', 'bank_account_number', 'bank_swift', 'mobile_money',
    'default_tax_rate', 'default_payment_days', 'default_quote_validity_days',
    'quote_prefix', 'invoice_prefix', 'credit_note_prefix', 'number_padding',
    'logo_path', 'signature_path', 'stamp_path', 'document_primary_color',
    'real_started_at', 'real_start_date', 'real_start_archive_path', 'real_started_by',
])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * Same defaults as the table, so that a company just created already
     * knows how to number its documents.
     */
    protected $attributes = [
        'default_currency' => 'CDF',
        'default_tax_rate' => 0,
        'default_payment_days' => 30,
        'default_quote_validity_days' => 30,
        'quote_prefix' => 'DEV',
        'invoice_prefix' => 'FAC',
        'credit_note_prefix' => 'AVO',
        'number_padding' => 5,
        'billing_exempt' => false,
    ];

    /**
     * The company the current request (or console task) works for.
     */
    public static function current(): self
    {
        return app(CurrentCompany::class)->get();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Read from the platform area, where no company is current: the company
     * scope is lifted, the foreign key already confines the rows.
     */
    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->withoutGlobalScope(CompanyScope::class);
    }

    public function platformEvents(): HasMany
    {
        return $this->hasMany(PlatformEvent::class);
    }

    /**
     * Same rules as subscriptionStatus(), in SQL, to filter lists.
     */
    #[Scope]
    protected function withSubscriptionStatus(Builder $query, SubscriptionStatus $status): void
    {
        $today = today()->toDateString();
        $paid = fn (Builder $query) => $query->whereNotNull('subscription_ends_at')->whereDate('subscription_ends_at', '>=', $today);
        $trial = fn (Builder $query) => $query->whereNotNull('trial_ends_at')->whereDate('trial_ends_at', '>=', $today);

        match ($status) {
            SubscriptionStatus::Exempt => $query->where('billing_exempt', true),
            SubscriptionStatus::Active => $query->where('billing_exempt', false)->where($paid),
            SubscriptionStatus::Trial => $query->where('billing_exempt', false)->whereNot($paid)->where($trial),
            SubscriptionStatus::Expired => $query->where('billing_exempt', false)->whereNot($paid)->whereNot($trial),
        };
    }

    public function termsAcceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'terms_accepted_by');
    }

    public function closureRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closure_requested_by');
    }

    /**
     * Paid days run to the end of subscription_ends_at; trial days to the
     * end of trial_ends_at (ADR 0003 § 1).
     */
    public function subscriptionStatus(): SubscriptionStatus
    {
        return match (true) {
            $this->billing_exempt => SubscriptionStatus::Exempt,
            $this->subscription_ends_at !== null && today()->lte($this->subscription_ends_at) => SubscriptionStatus::Active,
            $this->trial_ends_at !== null && today()->lte($this->trial_ends_at) => SubscriptionStatus::Trial,
            default => SubscriptionStatus::Expired,
        };
    }

    /**
     * Last day the company can work normally, or null when it has no end.
     */
    public function accessEndsOn(): ?Carbon
    {
        return match ($this->subscriptionStatus()) {
            SubscriptionStatus::Active => $this->subscription_ends_at,
            SubscriptionStatus::Trial => $this->trial_ends_at,
            default => null,
        };
    }

    public function hasAcceptedCurrentTerms(): bool
    {
        return $this->terms_version === config('konta360.terms_version');
    }

    public function isClosureRequested(): bool
    {
        return $this->closure_requested_at !== null && $this->closed_at === null;
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Disk holding the branding files (logo, signature, stamp). Private: a
     * signature or a stamp must never be downloadable without signing in.
     * They are served by CompanySettingController::assetResponse.
     */
    public const BRAND_DISK = 'local';

    public const BRAND_ASSETS = ['logo', 'signature', 'stamp'];

    /** The current document theme, preserved for companies without a choice. */
    public function documentPrimaryColor(): string
    {
        return $this->document_primary_color ?: '#4338ca';
    }

    /** A light tint derived from the chosen color for badges and subtle fills. */
    public function documentSoftColor(): string
    {
        $hex = ltrim($this->documentPrimaryColor(), '#');
        $channels = array_map('hexdec', str_split($hex, 2));
        $channels = array_map(fn (int $channel) => (int) round($channel * 0.12 + 255 * 0.88), $channels);

        return sprintf('#%02x%02x%02x', ...$channels);
    }

    public static function brandDisk(): Filesystem
    {
        return Storage::disk(self::BRAND_DISK);
    }

    /**
     * Absolute path of a branding file, or null when there is none.
     */
    public function brandAssetPath(string $type): ?string
    {
        $path = in_array($type, self::BRAND_ASSETS, true) ? $this->{$type.'_path'} : null;

        return $path && self::brandDisk()->exists($path) ? self::brandDisk()->path($path) : null;
    }

    /**
     * Display size, in pixels, of a branding image inside a box of
     * $maxWidth × $maxHeight, proportions kept. Set as explicit width and
     * height: dompdf does not reliably apply max-width/max-height to images.
     *
     * With $balanced (logos), every shape gets about the same visual weight:
     * the image aims at a fixed share of the box area, so a square logo is
     * not dwarfed by a wide one, nor a wide one squeezed to a thin strip.
     *
     * @return array{0: int, 1: int}|null
     */
    public function brandImageSize(string $type, int $maxWidth, int $maxHeight, bool $balanced = false): ?array
    {
        $path = $this->brandAssetPath($type);
        $size = $path ? @getimagesize($path) : false;
        if (! $size || $size[0] <= 0 || $size[1] <= 0) {
            return null;
        }

        $ratio = $size[0] / $size[1];
        $width = $balanced ? sqrt($maxWidth * $maxHeight * 0.55 * $ratio) : $maxWidth;
        $height = $width / $ratio;
        $scale = min(1, $maxWidth / $width, $maxHeight / $height);

        return [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];
    }

    /**
     * Folder of the branding disk holding this company's files.
     */
    public function storageDirectory(): string
    {
        return 'companies/'.$this->getKey();
    }

    protected function casts(): array
    {
        return [
            'default_tax_rate' => 'decimal:2',
            'default_payment_days' => 'integer',
            'default_quote_validity_days' => 'integer',
            'number_padding' => 'integer',
            'suspended_at' => 'datetime',
            'trial_ends_at' => 'date',
            'subscription_ends_at' => 'date',
            'billing_exempt' => 'boolean',
            'terms_accepted_at' => 'datetime',
            'closure_requested_at' => 'datetime',
            'closed_at' => 'datetime',
            'real_started_at' => 'datetime',
            'real_start_date' => 'date',
        ];
    }
}
