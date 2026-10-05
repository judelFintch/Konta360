<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Companies\Services\CompanyDataExporter;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->operator = User::factory()->platformAdmin()->create();
    $this->company = Company::factory()->create(['name' => 'Société Bêta']);
    $this->admin = User::factory()->forCompany($this->company)->create(['name' => 'Admin Bêta']);
    $this->admin->assignRole(Role::Administrateur->value);
    $this->data = $this->asCompany($this->company, fn () => seedCompanyData($this->admin, 'BETA'));

    // Another company whose data must never leak into the export or the purge.
    $this->other = Company::factory()->create();
    $this->otherAdmin = User::factory()->forCompany($this->other)->create();
    $this->asCompany($this->other, fn () => seedCompanyData($this->otherAdmin, 'GAMMA'));
});

function readZip(string $path): array
{
    $zip = new ZipArchive;
    $zip->open($path);
    $files = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $files[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
    }
    $zip->close();

    return $files;
}

it('exports every table of the company, and only its own rows', function () {
    $files = readZip(app(CompanyDataExporter::class)->export($this->company));

    foreach (CompanyDataExporter::TABLES as $table) {
        expect($files)->toHaveKey("donnees/{$table}.csv");
    }
    expect($files)->toHaveKeys(['LISEZMOI.txt', 'societe.csv', 'utilisateurs.csv', 'donnees/bank_reconciliation_transactions.csv'])
        ->and($files['donnees/invoices.csv'])->toContain('FAC-BETA')->not->toContain('FAC-GAMMA')
        ->and($files['donnees/parties.csv'])->toContain('Client BETA')->not->toContain('Client GAMMA')
        ->and($files['utilisateurs.csv'])->toContain('Admin Bêta')->not->toContain($this->otherAdmin->email)
        ->and($files['utilisateurs.csv'])->not->toContain('password')
        ->and($files['donnees/document_sequences.csv'])->toStartWith("\xEF\xBB\xBFid;company_id;type;year;last_number");
});

it('lets an administrator download the export', function () {
    $this->actingAs($this->admin)->get(route('administration.data.show'))->assertOk()->assertSee('Télécharger l’export');
    $this->actingAs($this->admin)->get(route('administration.data.export'))
        ->assertOk()
        ->assertDownload('konta360-societe-beta-'.now()->format('Y-m-d').'.zip');
});

it('keeps the data pages to administrators', function () {
    $accountant = User::factory()->forCompany($this->company)->create();
    $accountant->assignRole(Role::Comptable->value);

    $this->actingAs($accountant)->get(route('administration.data.show'))->assertForbidden();
    $this->actingAs($accountant)->get(route('administration.data.export'))->assertForbidden();
});

it('asks administrators to accept new terms and records who accepted them', function () {
    $this->company->forceFill(['terms_version' => '2020-01-01'])->save();

    $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('ont été mises à jour');
    $this->actingAs($this->admin)->post(route('administration.data.terms.accept'))->assertRedirect();

    $company = $this->company->refresh();
    expect($company->hasAcceptedCurrentTerms())->toBeTrue()
        ->and($company->terms_accepted_by)->toBe($this->admin->id);
    $this->actingAs($this->admin)->get(route('dashboard'))->assertDontSee('ont été mises à jour');
});

it('serves the legal pages to everyone', function () {
    $this->get(route('legal.terms'))->assertOk()->assertSee('Conditions générales d’utilisation');
    $this->get(route('legal.privacy'))->assertOk()->assertSee('Politique de confidentialité');
});

it('requests a closure only with the password and the exact company name', function () {
    $this->actingAs($this->admin)->post(route('administration.data.closure.request'), [
        'password' => 'wrong', 'company_name' => 'Société Bêta',
    ])->assertSessionHasErrors('password');
    $this->actingAs($this->admin)->post(route('administration.data.closure.request'), [
        'password' => 'password', 'company_name' => 'Societe Beta',
    ])->assertSessionHasErrors('company_name');
    expect($this->company->refresh()->isClosureRequested())->toBeFalse();

    $this->actingAs($this->admin)->post(route('administration.data.closure.request'), [
        'password' => 'password', 'company_name' => 'Société Bêta',
    ])->assertSessionHasNoErrors();
    expect($this->company->refresh()->isClosureRequested())->toBeTrue()
        ->and($this->company->closure_requested_by)->toBe($this->admin->id);

    $this->actingAs($this->admin)->delete(route('administration.data.closure.cancel'))->assertRedirect();
    expect($this->company->refresh()->isClosureRequested())->toBeFalse();
});

it('closes a company on request: access ends, data is kept', function () {
    $this->actingAs($this->operator)->patch(route('platform.companies.close', $this->company))->assertStatus(409);

    $this->company->forceFill(['closure_requested_at' => now(), 'closure_requested_by' => $this->admin->id])->save();
    $this->actingAs($this->operator)->patch(route('platform.companies.close', $this->company))->assertRedirect();

    $company = $this->company->refresh();
    expect($company->isClosed())->toBeTrue()
        ->and($company->isSuspended())->toBeTrue();
    $this->actingAs($this->admin->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    $this->actingAs($this->operator)->patch(route('platform.companies.reactivate', $company))->assertStatus(409);
    $this->asCompany($company, fn () => expect(Invoice::count())->toBe(2));
});

it('refuses to purge a company before the end of the retention period', function () {
    Date::setTestNow('2026-10-06');
    $this->company->forceFill(['closure_requested_at' => now(), 'closed_at' => now()])->save();

    $this->artisan('konta360:purge-company', ['company' => $this->company->id])
        ->expectsConfirmation('Confirmer la suppression définitive ?', 'yes')
        ->expectsOutputToContain('2036')
        ->assertFailed();

    expect(Company::find($this->company->id))->not->toBeNull();
});

it('refuses to purge a company that is not closed', function () {
    $this->artisan('konta360:purge-company', ['company' => $this->company->id, '--ignore-retention' => true])
        ->expectsConfirmation('Confirmer la suppression définitive ?', 'yes')
        ->assertFailed();

    expect(Company::find($this->company->id))->not->toBeNull();
});

it('purges a closed company and all of its data once the retention period is over', function () {
    $this->company->forceFill(['closure_requested_at' => now()->subYears(11), 'closed_at' => now()->subYears(11)])->save();
    $tables = [...CompanyDataExporter::TABLES];
    $otherCounts = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->where('company_id', $this->other->id)->count()]);

    $this->artisan('konta360:purge-company', ['company' => $this->company->id])
        ->expectsConfirmation('Confirmer la suppression définitive ?', 'yes')
        ->assertSuccessful();

    expect(Company::find($this->company->id))->toBeNull()
        ->and(User::where('company_id', $this->company->id)->exists())->toBeFalse()
        ->and(User::find($this->admin->id))->toBeNull()
        ->and(DB::table('model_has_roles')->where('model_id', $this->admin->id)->exists())->toBeFalse();
    foreach ($tables as $table) {
        expect(DB::table($table)->where('company_id', $this->company->id)->count())->toBe(0, $table)
            ->and(DB::table($table)->where('company_id', $this->other->id)->count())->toBe($otherCounts[$table], $table);
    }
});
