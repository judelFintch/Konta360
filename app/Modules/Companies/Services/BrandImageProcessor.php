<?php

namespace App\Modules\Companies\Services;

use GdImage;
use InvalidArgumentException;

/**
 * Cleans the logo, signature and stamp a company uploads, so that they
 * print well whatever the original file:
 *
 * - turns phone photos the right way up (EXIF orientation);
 * - crops the empty margins (transparent or plain-coloured border), which
 *   otherwise make a logo look tiny;
 * - scales down to a size that is sharp in print but light in the PDF;
 * - saves as PNG, which keeps transparency and is read reliably by dompdf.
 *
 * Uses GD only, available on standard cPanel hosting.
 */
class BrandImageProcessor
{
    /** Largest stored size, in pixels, per kind of image. */
    public const LIMITS = [
        'logo' => [900, 450],
        'signature' => [700, 350],
        'stamp' => [600, 600],
    ];

    /** Refuses images too large to decode safely in memory. */
    private const MAX_PIXELS = 40_000_000;

    /** Space kept around the content after cropping, as a share of its size. */
    private const PADDING = 0.03;

    /**
     * @return array{png: string, width: int, height: int, warnings: list<string>}
     *
     * @throws InvalidArgumentException when the file is not a usable image.
     */
    public function process(string $path, string $type): array
    {
        [$maxWidth, $maxHeight] = self::LIMITS[$type] ?? throw new InvalidArgumentException("Type d’image inconnu : {$type}.");

        $info = @getimagesize($path);
        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            throw new InvalidArgumentException('Ce fichier n’est pas une image PNG, JPG ou WEBP lisible.');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException('Cette image est trop grande (plus de 40 millions de pixels). Réduisez-la avant de l’envoyer.');
        }

        $image = match ($info[2]) {
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
        };
        if (! $image instanceof GdImage) {
            throw new InvalidArgumentException('Cette image est endommagée ou dans un format non pris en charge.');
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        if ($info[2] === IMAGETYPE_JPEG) {
            $image = $this->applyOrientation($image, $path);
        }

        // Cropping works on a reduced copy: fast, and precise enough.
        $image = $this->fit($image, 1600, 1600);
        $analysis = $this->analyse($image);
        if ($analysis['box']) {
            $image = $this->crop($image, $analysis['box']);
        }

        $warnings = [];
        if ($type === 'logo' && max(imagesx($image), imagesy($image)) < 300) {
            $warnings[] = 'Le logo est de petite taille ('.imagesx($image).' × '.imagesy($image).' pixels) : il risque d’être flou à l’impression. Préférez une image d’au moins 600 pixels de large.';
        }
        if ($type === 'logo' && $analysis['transparent'] && $analysis['luminance'] !== null && $analysis['luminance'] > 225) {
            $warnings[] = 'Le logo est clair sur fond transparent : il sera peu visible sur le fond blanc des documents. Utilisez de préférence une version foncée ou sur fond coloré.';
        }

        $image = $this->fit($image, $maxWidth, $maxHeight);

        ob_start();
        imagepng($image, null, 9);
        $png = (string) ob_get_clean();

        return ['png' => $png, 'width' => imagesx($image), 'height' => imagesy($image), 'warnings' => $warnings];
    }

    private function applyOrientation(GdImage $image, string $path): GdImage
    {
        $orientation = function_exists('exif_read_data') ? (@exif_read_data($path)['Orientation'] ?? 1) : 1;
        $angle = match ((int) $orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        return $rotated;
    }

    /**
     * Scales down (never up) to fit in the box, keeping the proportions and
     * the transparency.
     */
    private function fit(GdImage $image, int $maxWidth, int $maxHeight): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxWidth / $width, $maxHeight / $height);
        if ($scale >= 1) {
            return $image;
        }

        return $this->resample($image, 0, 0, $width, $height, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
    }

    /**
     * @param  array{0: int, 1: int, 2: int, 3: int}  $box  left, top, right, bottom (inclusive)
     */
    private function crop(GdImage $image, array $box): GdImage
    {
        [$left, $top, $right, $bottom] = $box;
        $width = $right - $left + 1;
        $height = $bottom - $top + 1;
        $padding = (int) ceil(max($width, $height) * self::PADDING);

        $canvas = $this->blank($width + 2 * $padding, $height + 2 * $padding);
        imagecopy($canvas, $image, $padding, $padding, $left, $top, $width, $height);

        return $canvas;
    }

    private function resample(GdImage $image, int $x, int $y, int $width, int $height, int $newWidth, int $newHeight): GdImage
    {
        $canvas = $this->blank($newWidth, $newHeight);
        imagecopyresampled($canvas, $image, 0, 0, $x, $y, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    private function blank(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        return $canvas;
    }

    /**
     * Finds the content inside a plain border. The background is read from
     * the four corners: transparent, or a single colour (white, cream…).
     * No crop when the corners disagree (photo, full-bleed design).
     *
     * @return array{box: ?array{0: int, 1: int, 2: int, 3: int}, transparent: bool, luminance: ?float}
     */
    private function analyse(GdImage $image): array
    {
        $probe = $this->fit($image, 400, 400);
        $width = imagesx($probe);
        $height = imagesy($probe);
        $pixel = function (int $x, int $y) use ($probe): array {
            $value = imagecolorat($probe, $x, $y);

            return [($value >> 16) & 0xFF, ($value >> 8) & 0xFF, $value & 0xFF, ($value >> 24) & 0x7F];
        };

        $corners = [$pixel(0, 0), $pixel($width - 1, 0), $pixel(0, $height - 1), $pixel($width - 1, $height - 1)];
        $transparent = count(array_filter($corners, fn (array $c) => $c[3] >= 100)) >= 3;
        $background = null;
        if (! $transparent) {
            $background = array_map(fn (int $i) => array_sum(array_column($corners, $i)) / 4, [0, 1, 2]);
            foreach ($corners as $corner) {
                if ($this->distance($corner, $background) > 40) {
                    return ['box' => null, 'transparent' => false, 'luminance' => null];
                }
            }
        }

        $isContent = fn (array $c) => $c[3] < 100 && ($transparent || $this->distance($c, $background) > 40);

        $left = $width;
        $right = -1;
        $top = $height;
        $bottom = -1;
        $luminance = 0.0;
        $count = 0;
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $c = $pixel($x, $y);
                if (! $isContent($c)) {
                    continue;
                }
                $left = min($left, $x);
                $right = max($right, $x);
                $top = min($top, $y);
                $bottom = max($bottom, $y);
                $luminance += 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
                $count++;
            }
        }

        if ($count === 0) {
            return ['box' => null, 'transparent' => $transparent, 'luminance' => null];
        }

        // Back to the full-size image, one probe pixel wider on each side.
        $scaleX = imagesx($image) / $width;
        $scaleY = imagesy($image) / $height;
        $box = [
            max(0, (int) floor(($left - 1) * $scaleX)),
            max(0, (int) floor(($top - 1) * $scaleY)),
            min(imagesx($image) - 1, (int) ceil(($right + 2) * $scaleX) - 1),
            min(imagesy($image) - 1, (int) ceil(($bottom + 2) * $scaleY) - 1),
        ];

        return ['box' => $box, 'transparent' => $transparent, 'luminance' => $luminance / $count];
    }

    /**
     * @param  array<int, int|float>  $a
     * @param  array<int, int|float>  $b
     */
    private function distance(array $a, array $b): float
    {
        return sqrt(($a[0] - $b[0]) ** 2 + ($a[1] - $b[1]) ** 2 + ($a[2] - $b[2]) ** 2);
    }
}
