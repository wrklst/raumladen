<?php

namespace App\Settings;

use Illuminate\Support\Facades\Storage;

/**
 * What a shop owner sets under Shop settings, Appearance, in the form the
 * storefront, the invoice and the mail templates need it. Themes decide where
 * and how these are shown.
 */
class Appearance
{
    public const string DISK = 'public';

    /**
     * Address of the logo for web pages and emails, or null without a logo.
     */
    public static function logoUrl(): ?string
    {
        return self::url('shop.appearance.logo');
    }

    /**
     * Address of the shop's own icon (browser tab, home screen), or null for the built-in one.
     */
    public static function iconUrl(): ?string
    {
        return self::url('shop.appearance.icon');
    }

    /**
     * Address of an uploaded image setting, or null when nothing is uploaded.
     */
    public static function url(string $setting): ?string
    {
        $path = self::path($setting);

        return $path === null ? null : Storage::disk(self::DISK)->url($path);
    }

    /**
     * The logo embedded as a data URI: the invoice PDF is rendered without
     * network access.
     */
    public static function logoDataUri(): ?string
    {
        $path = self::path('shop.appearance.logo');

        if ($path === null) {
            return null;
        }

        $disk = Storage::disk(self::DISK);

        return 'data:'.$disk->mimeType($path).';base64,'.base64_encode((string) $disk->get($path));
    }

    /**
     * The closing lines of every email; the seller's name unless set otherwise.
     */
    public static function mailSignature(): string
    {
        return trim((string) config('shop.appearance.mail_signature')) ?: (string) config('shop.seller.name');
    }

    private static function path(string $setting): ?string
    {
        $path = (string) config($setting);

        return $path !== '' && Storage::disk(self::DISK)->exists($path) ? $path : null;
    }
}
