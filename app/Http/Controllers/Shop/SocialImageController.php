<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Theme\Theme;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * The picture of a product for link previews (og:image), drawn by the theme
 * (see Theme::socialImage()). Drawn once per version of the product and of
 * the theme's drawing, then served from storage.
 */
class SocialImageController extends Controller
{
    public function __invoke(Product $product): Response
    {
        $theme = Theme::current();
        $draw = $theme->socialImage();

        abort_if($draw === null, 404);

        // ponytail: older versions stay in storage/app/private/social, a few kilobytes each; prune when it matters.
        $file = sprintf('social/%d-%s-%d-%d.png', $product->id, app()->getLocale(), $product->updated_at->timestamp, filemtime($theme->socialImagePath()));
        $disk = Storage::disk('local');

        if (! $disk->exists($file)) {
            ob_start();
            imagepng($draw($product));
            $disk->put($file, (string) ob_get_clean());
        }

        return response((string) $disk->get($file), headers: [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
