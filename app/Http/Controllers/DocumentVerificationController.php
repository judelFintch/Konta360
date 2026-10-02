<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use App\Models\Invoice;
use App\Modules\Documents\Services\CommercialDocumentPresenter;
use Illuminate\View\View;

/**
 * Public page reached by scanning the QR code printed on a document.
 * The URL is signed, so only codes issued by the application resolve.
 */
class DocumentVerificationController extends Controller
{
    public function __invoke(string $type, int $id, CommercialDocumentPresenter $presenter): View
    {
        $model = CommercialDocumentPresenter::TYPES[$type] ?? abort(404);
        $document = $model::query()->with(['party', 'lines'])->findOrFail($id);
        abort_unless($presenter->isVerifiable($document), 404);

        return view('documents.verify', [
            'company' => CompanySetting::current(),
            'document' => $document,
            'documentType' => ['quote' => 'Devis', 'invoice' => 'Facture', 'credit_note' => 'Avoir'][$type],
            'fingerprint' => $presenter->fingerprint($document),
            'cancelled' => $presenter->watermark($document) !== null,
            'balanceDue' => $document instanceof Invoice ? $document->balanceDue() : null,
        ]);
    }
}
