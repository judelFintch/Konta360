<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Administration\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Administration\Enums\Role;
use App\Modules\Companies\Services\BrandImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Draws a test image and returns its file path.
 *
 * @param  array{0: int, 1: int, 2: int, 3?: int}|null  $background  RGB(A), null for transparent
 * @param  list<array{0: int, 1: int, 2: int, 3: int, 4: array{0: int, 1: int, 2: int}}>  $shapes  x1, y1, x2, y2, colour
 */
function drawImage(int $width, int $height, ?array $background, array $shapes = [], string $format = 'png'): string
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, $background
        ? imagecolorallocatealpha($image, $background[0], $background[1], $background[2], $background[3] ?? 0)
        : imagecolorallocatealpha($image, 0, 0, 0, 127));
    foreach ($shapes as [$x1, $y1, $x2, $y2, $colour]) {
        imagefilledrectangle($image, $x1, $y1, $x2, $y2, imagecolorallocate($image, ...$colour));
    }

    $path = tempnam(sys_get_temp_dir(), 'brand').'.'.$format;
    $format === 'jpg' ? imagejpeg($image, $path, 95) : imagepng($image, $path);

    return $path;
}

function companySettings(Company $company): array
{
    return $company->only([
        'name', 'legal_form', 'tax_identifier', 'trade_register', 'address', 'email',
        'phone', 'website', 'default_currency', 'invoice_footer', 'default_tax_rate',
        'default_payment_days', 'default_quote_validity_days', 'quote_prefix',
        'invoice_prefix', 'credit_note_prefix', 'number_padding',
    ]);
}

beforeEach(function () {
    $this->images = app(BrandImageProcessor::class);
});

it('crops the transparent margins around a logo', function () {
    // A 400 × 100 logo lost in a 1500 × 1500 transparent canvas.
    $result = $this->images->process(drawImage(1500, 1500, null, [[550, 700, 949, 799, [30, 64, 175]]]), 'logo');

    expect($result['width'])->toBeBetween(400, 450)
        ->and($result['height'])->toBeBetween(100, 140)
        ->and($result['warnings'])->toBe([]);

    $png = imagecreatefromstring($result['png']);
    expect(imagecolorat($png, 0, 0) >> 24)->toBe(127); // still transparent around
});

it('crops a plain white border of a JPG and saves it as PNG', function () {
    $result = $this->images->process(drawImage(1200, 900, [255, 255, 255], [[300, 300, 899, 499, [0, 0, 0]]], 'jpg'), 'logo');

    expect(substr($result['png'], 1, 3))->toBe('PNG')
        ->and($result['width'])->toBeBetween(600, 680)
        ->and($result['height'])->toBeBetween(200, 260);
});

it('does not crop an image without a plain border', function () {
    // Four different corners: a photo or a full-bleed design.
    $path = drawImage(800, 400, [255, 255, 255], [
        [0, 0, 399, 199, [200, 0, 0]], [400, 0, 799, 199, [0, 200, 0]], [0, 200, 399, 399, [0, 0, 200]],
    ]);

    expect($this->images->process($path, 'logo'))->width->toBe(800)->height->toBe(400);
});

it('scales large images down, keeping their proportions', function () {
    $result = $this->images->process(drawImage(4000, 1000, [10, 10, 10]), 'logo');

    expect([$result['width'], $result['height']])->toBe([900, 225]);
});

it('warns about a small logo and a light logo on a transparent background', function () {
    expect($this->images->process(drawImage(120, 60, [0, 0, 0]), 'logo')['warnings'][0])->toContain('petite taille');

    $light = $this->images->process(drawImage(1000, 400, null, [[100, 100, 899, 299, [250, 250, 250]]]), 'logo');
    expect(implode(' ', $light['warnings']))->toContain('clair sur fond transparent');
});

it('refuses a file that is not a readable image', function () {
    $path = tempnam(sys_get_temp_dir(), 'brand');
    file_put_contents($path, 'pas une image');

    $this->images->process($path, 'logo');
})->throws(InvalidArgumentException::class);

it('gives every logo shape a balanced display size', function () {
    Storage::fake('local');
    $company = Company::current();
    $sizeOf = function (int $width, int $height) use ($company) {
        Storage::disk('local')->put('logo.png', file_get_contents(drawImage($width, $height, [0, 0, 0])));
        $company->forceFill(['logo_path' => 'logo.png']);

        return $company->brandImageSize('logo', 190, 64, balanced: true);
    };

    expect($sizeOf(500, 500))->toBe([64, 64])        // square: full height
        ->and($sizeOf(800, 200))->toBe([164, 41])     // 4:1, not a thin strip
        ->and($sizeOf(1600, 100))->toBe([190, 12])    // very wide: full width
        ->and($sizeOf(200, 400))->toBe([32, 64]);     // tall: full height

    $company->forceFill(['logo_path' => null]);
    expect($company->brandImageSize('logo', 190, 64))->toBeNull();
});

describe('upload', function () {
    beforeEach(function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrateur->value);
        Storage::fake('local');
    });

    it('stores the cleaned logo and shows its preview and warnings', function () {
        $data = companySettings(Company::current());
        $data['logo'] = new UploadedFile(drawImage(2000, 2000, null, [[900, 900, 1099, 999, [30, 64, 175]]]), 'logo.png', 'image/png', null, true);

        $this->actingAs($this->admin)->put(route('administration.company.update'), $data)
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warnings', fn (array $warnings) => str_contains($warnings[0], 'petite taille'));

        $company = Company::current()->refresh();
        expect($company->logo_path)->toMatch('/^companies\/\d+\/logo-[A-Za-z0-9]{32}\.png$/');
        expect(getimagesize(Storage::disk('local')->path($company->logo_path))[0])->toBeLessThan(260);

        $this->get(route('administration.company.edit'))->assertSee('Aperçu sur vos devis et factures');
    });

    it('keeps the current logo when the new file cannot be read', function () {
        $company = Company::current();
        Storage::disk('local')->put('companies/1/ancien.png', file_get_contents(drawImage(600, 200, [0, 0, 0])));
        $company->forceFill(['logo_path' => 'companies/1/ancien.png'])->save();
        $broken = tempnam(sys_get_temp_dir(), 'brand').'.png';
        file_put_contents($broken, "\x89PNG\r\n\x1a\n".str_repeat('x', 100));

        $data = companySettings($company);
        $data['logo'] = new UploadedFile($broken, 'logo.png', 'image/png', null, true);
        $this->actingAs($this->admin)->put(route('administration.company.update'), $data)->assertSessionHasErrors('logo');

        expect($company->refresh()->logo_path)->toBe('companies/1/ancien.png');
        Storage::disk('local')->assertExists('companies/1/ancien.png');
    });

    it('prints the logo with explicit sizes that dompdf respects', function () {
        $company = Company::current();
        Storage::disk('local')->put('companies/1/logo.png', file_get_contents(drawImage(800, 200, [0, 0, 0])));
        $company->forceFill(['logo_path' => 'companies/1/logo.png'])->save();

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertSee('style="width: 115px; height: 29px"', false);
    });
});

it('cleans the images uploaded before, once', function () {
    Storage::fake('local');
    $company = Company::current();
    Storage::disk('local')->put('companies/1/old-logo.png', file_get_contents(drawImage(2000, 1000, null, [[500, 400, 1499, 599, [0, 0, 0]]])));
    $company->forceFill(['logo_path' => 'companies/1/old-logo.png'])->save();

    $this->artisan('konta360:normalize-brand-images')->expectsOutputToContain('1 image(s) optimisée(s)')->assertSuccessful();

    $path = $company->refresh()->logo_path;
    expect($path)->toMatch('/logo-[A-Za-z0-9]{32}\.png$/');
    Storage::disk('local')->assertMissing('companies/1/old-logo.png');
    expect(getimagesize(Storage::disk('local')->path($path))[0])->toBeLessThanOrEqual(900);

    $this->artisan('konta360:normalize-brand-images')->expectsOutputToContain('0 image(s) optimisée(s)');
    expect($company->refresh()->logo_path)->toBe($path);
});
