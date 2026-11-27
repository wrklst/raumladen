<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The pictures of products as the shop serves them: WebP, upright, no wider
 * than a screen shows them, and beside each a small one for narrow screens
 * ("name.webp" and "name-600.webp"; resources/js/lib/picture.ts names both).
 */
class Picture
{
    private const array WIDTHS = ['-600' => 600, '' => 2000];

    /**
     * Stores the picture in the file given on the public disk and returns its
     * path. A name that is already there is kept, so a name made of the
     * content is written once.
     */
    public static function store(string $file, string $name): string
    {
        $disk = Storage::disk('public');
        $path = "products/{$name}.webp";

        if ($disk->exists($path)) {
            return $path;
        }

        $image = @imagecreatefromstring((string) file_get_contents($file));

        if ($image === false) {
            throw new RuntimeException("{$file} is not a picture.");
        }

        // A photo lying on its side says so in its EXIF data, which WebP made here does not carry.
        $exif = function_exists('exif_read_data') ? @exif_read_data($file) : false;
        $image = imagerotate($image, [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0, 0) ?: $image;
        imagepalettetotruecolor($image);

        foreach (self::WIDTHS as $suffix => $width) {
            $scaled = imagesx($image) > $width ? imagescale($image, $width, mode: IMG_BICUBIC) : $image;

            if ($scaled === false) {
                throw new RuntimeException("{$file} cannot be scaled.");
            }

            imagesavealpha($scaled, true);
            ob_start();
            imagewebp($scaled, null, 80);
            $disk->put("products/{$name}{$suffix}.webp", (string) ob_get_clean());
        }

        return $path;
    }

    /**
     * @param  array<int, string|null>  $paths  Pictures no product shows any more; their small ones go with them.
     */
    public static function delete(array $paths): void
    {
        $paths = array_filter($paths);

        Storage::disk('public')->delete([...$paths, ...array_map(fn (string $path): string => (string) preg_replace('/\.webp$/', '-600.webp', $path), $paths)]);
    }
}
