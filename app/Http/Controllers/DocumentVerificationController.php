<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Modules\Companies\Scopes\CompanyScope;
use App\Modules\Companies\Services\CurrentCompany;
use App\Modules\Documents\Services\CommercialDocumentPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public page reached by scanning the QR code printed on a document.
 * Only links carrying the token issued by the application resolve.
 *
 * Nobody is signed in here, so the company is the one that issued the
 * document. Document ids are global, so the token printed on documents
 * issued before multi-company support stays valid (ADR 0002 § 10).
 */
class DocumentVerificationController extends Controller
{
    public function __construct(
        private readonly CommercialDocumentPresenter $presenter,
        private readonly CurrentCompany $currentCompany,
    ) {}

    public function __invoke(string $type, int $id, string $token): View
    {
        $document = $this->resolve($type, $id, $token);
        $document->load(['party', 'lines']);

        return view('documents.verify', [
            'company' => $this->currentCompany->get(),
            'document' => $document,
            'documentType' => ['quote' => 'Devis', 'invoice' => 'Facture', 'credit_note' => 'Avoir'][$type],
            'verification' => compact('type', 'id', 'token'),
            'fingerprint' => $this->presenter->fingerprint($document),
            'cancelled' => $this->presenter->watermark($document) !== null,
            'balanceDue' => $document instanceof Invoice ? $document->balanceDue() : null,
        ]);
    }

    public function logo(string $type, int $id, string $token): BinaryFileResponse
    {
        $this->resolve($type, $id, $token);

        return CompanySettingController::assetResponse($this->currentCompany->get(), 'logo');
    }

    /**
     * Finds the verified document and works as its company for the rest of
     * the request.
     */
    private function resolve(string $type, int $id, string $token): Model
    {
        abort_unless(hash_equals($this->presenter->verificationToken($type, $id), $token), 403, 'Lien de vérification invalide.');
        $model = CommercialDocumentPresenter::TYPES[$type] ?? abort(404);
        $document = $model::query()->withoutGlobalScope(CompanyScope::class)->with('company')->findOrFail($id);
        $this->currentCompany->set($document->company);
        abort_unless($this->presenter->isVerifiable($document), 404);

        return $document;
    }
}
