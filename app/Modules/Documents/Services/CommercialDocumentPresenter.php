<?php

namespace App\Modules\Documents\Services;

use App\Models\CompanySetting;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Quote;
use App\Modules\Invoices\Enums\InvoiceStatus;
use App\Modules\Quotes\Enums\QuoteStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Database\Eloquent\Model;
use NumberFormatter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds the control and verification elements printed on quotes,
 * invoices and credit notes: integrity fingerprint, verification QR code,
 * amount in words, VAT breakdown and watermark.
 */
class CommercialDocumentPresenter
{
    public const TYPES = [
        'quote' => Quote::class,
        'invoice' => Invoice::class,
        'credit_note' => CreditNote::class,
    ];

    /**
     * Data shared by every rendering of a commercial document.
     */
    public function present(Quote|Invoice|CreditNote $document): array
    {
        $document->loadMissing(['party', 'lines', 'creator']);
        $verifiable = $this->isVerifiable($document);
        $verificationUrl = $verifiable ? $this->verificationUrl($document) : null;

        return [
            'company' => CompanySetting::current(),
            'fingerprint' => $verifiable ? $this->fingerprint($document) : null,
            'verificationUrl' => $verificationUrl,
            'qrCode' => $verificationUrl ? $this->qrCode($verificationUrl) : null,
            'amountInWords' => $this->amountInWords((float) $document->total, $document->currency),
            'taxBreakdown' => $this->taxBreakdown($document),
            'watermark' => $this->watermark($document),
        ];
    }

    /**
     * A4 PDF download with « Page x / y » stamped on every page, which
     * dompdf cannot do from CSS alone.
     */
    public function download(array $data, string $filename): Response
    {
        $pdf = Pdf::loadView('documents.commercial', $data)->setPaper('a4');
        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($canvas->get_width() - 80, $canvas->get_height() - 30, 'Page {PAGE_NUM} / {PAGE_COUNT}', $font, 7, [0.42, 0.45, 0.5]);

        return $pdf->download($filename);
    }

    public function typeOf(Model $document): string
    {
        return array_search($document::class, self::TYPES, true);
    }

    /**
     * A draft invoice has no definitive number yet and must not circulate
     * as a valid document, so it gets no verification code.
     */
    public function isVerifiable(Model $document): bool
    {
        return filled($document->number)
            && ! ($document instanceof Invoice && $document->status === InvoiceStatus::Draft);
    }

    /**
     * SHA-256 over the legally significant content, shortened for printing.
     * Any change to the number, date, customer, amounts or lines changes it.
     */
    public function fingerprint(Quote|Invoice|CreditNote $document): string
    {
        $document->loadMissing(['party', 'lines']);

        $payload = [
            'type' => $this->typeOf($document),
            'number' => $document->number,
            'issue_date' => $document->issue_date->toDateString(),
            'party' => [$document->party_id, $document->party->name, $document->party->tax_identifier],
            'currency' => $document->currency,
            'totals' => array_map(fn ($value) => number_format((float) $value, 2, '.', ''), [
                $document->subtotal, $document->discount_total, $document->tax_total, $document->total,
            ]),
            'lines' => $document->lines->map(fn ($line) => [
                $line->position,
                $line->sku,
                $line->description,
                number_format((float) $line->quantity, 3, '.', ''),
                number_format((float) $line->unit_price, 2, '.', ''),
                number_format((float) $line->discount_rate, 2, '.', ''),
                number_format((float) $line->tax_rate, 2, '.', ''),
                number_format((float) $line->total, 2, '.', ''),
            ])->all(),
        ];

        $hash = strtoupper(substr(hash('sha256', json_encode($payload)), 0, 16));

        return implode('-', str_split($hash, 4));
    }

    /**
     * Short public link printed as a QR code. The token is an HMAC of the
     * document identity: it does not depend on the scheme or host the link
     * is opened with (http/https, proxy, www), and keeps the QR code small.
     */
    public function verificationUrl(Model $document): string
    {
        $type = $this->typeOf($document);

        return route('documents.verify', [
            'type' => $type,
            'id' => $document->getKey(),
            'token' => $this->verificationToken($type, $document->getKey()),
        ]);
    }

    public function verificationToken(string $type, int|string $id): string
    {
        return substr(hash_hmac('sha256', $type.'|'.$id, config('app.key')), 0, 20);
    }

    public function amountInWords(float $amount, string $currency): string
    {
        [$major, $minor] = match ($currency) {
            'USD' => ['dollar américain', 'cent'],
            'EUR' => ['euro', 'centime'],
            'XAF', 'XOF' => ['franc CFA', 'centime'],
            default => ['franc congolais', 'centime'],
        };

        $formatter = new NumberFormatter('fr', NumberFormatter::SPELLOUT);
        $units = (int) floor(round($amount, 2));
        $cents = (int) round(($amount - $units) * 100);

        $words = $formatter->format($units);
        // « un million de francs », « deux milliards de dollars »
        $words .= preg_match('/(million|milliard)s?$/', $words) ? ' de ' : ' ';
        $words .= $this->plural($major, $units);
        if ($cents > 0) {
            $words .= ' et '.$formatter->format($cents).' '.$this->plural($minor, $cents);
        }

        return ucfirst($words);
    }

    /**
     * Net taxable base and tax amount grouped by rate.
     *
     * @return array<int, array{rate: float, base: float, tax: float}>
     */
    public function taxBreakdown(Quote|Invoice|CreditNote $document): array
    {
        return $document->lines
            ->groupBy(fn ($line) => number_format((float) $line->tax_rate, 2, '.', ''))
            ->map(fn ($lines, $rate) => [
                'rate' => (float) $rate,
                'base' => round($lines->sum(fn ($line) => (float) $line->subtotal - (float) $line->discount_amount), 2),
                'tax' => round($lines->sum(fn ($line) => (float) $line->tax_amount), 2),
            ])
            ->sortBy('rate')
            ->values()
            ->all();
    }

    public function watermark(Model $document): ?string
    {
        return match (true) {
            $document instanceof Invoice && $document->status === InvoiceStatus::Draft => 'Brouillon',
            $document instanceof Invoice && $document->status === InvoiceStatus::Cancelled => 'Annulée',
            $document instanceof Quote && $document->status === QuoteStatus::Draft => 'Brouillon',
            $document instanceof Quote && $document->status === QuoteStatus::Cancelled => 'Annulé',
            $document instanceof Quote && $document->status === QuoteStatus::Rejected => 'Refusé',
            default => null,
        };
    }

    private function qrCode(string $data): string
    {
        $options = new QROptions([
            // PNG rather than the library's default SVG: dompdf renders SVG poorly.
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => EccLevel::M,
            'scale' => 6,
            'quietzoneSize' => 2,
            'outputBase64' => true,
        ]);

        return (new QRCode($options))->render($data);
    }

    private function plural(string $words, int $count): string
    {
        if ($count < 2) {
            return $words;
        }

        return implode(' ', array_map(
            fn ($word) => $word === 'CFA' || str_ends_with($word, 's') ? $word : $word.'s',
            explode(' ', $words)
        ));
    }
}
