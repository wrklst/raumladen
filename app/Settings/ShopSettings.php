<?php

namespace App\Settings;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Shop settings made in the backend instead of the environment.
 *
 * Each field stands for one config path. Saved values live in the settings
 * table and are written over config() when the application boots, so the
 * rest of the code keeps reading config('shop...') and config('mail...') and
 * never knows where a value came from. What is not saved falls back to the
 * config files. Secrets (payment key, mail password) are encrypted with
 * APP_KEY and never sent back to the browser.
 *
 * The environment keeps the basics only: app key and URL, database, cache,
 * session, queue.
 */
class ShopSettings
{
    private const string CACHE_KEY = 'shop:settings';

    /**
     * Groups of fields, in the order of the settings page.
     *
     * @return array<string, array{label: string, description: string, fields: array<int, array<string, mixed>>}>
     */
    public static function groups(): array
    {
        $text = fn (string $name, string $path, string $label, int $max = 255, bool $required = false): array => [
            'name' => $name, 'path' => $path, 'type' => 'text', 'label' => $label,
            'rules' => [$required ? 'required' : 'nullable', 'string', 'max:'.$max],
        ];

        $account = fn (string $name, string $path, string $label): array => [
            'name' => $name, 'path' => $path, 'type' => 'text', 'label' => $label,
            'rules' => ['required', 'digits_between:4,9'],
        ];

        $zones = [];

        foreach (config('shop.shipping') as $i => $zone) {
            $code = strtolower($zone['code']);
            $zones[] = ['name' => "shipping_{$code}_price", 'path' => "shop.shipping.{$i}.price_cents", 'type' => 'money', 'label' => $zone['code'].': '.__('shipping price'), 'rules' => ['required', 'numeric', 'min:0', 'max:1000']];
            $zones[] = ['name' => "shipping_{$code}_free_from", 'path' => "shop.shipping.{$i}.free_from_cents", 'type' => 'money', 'label' => $zone['code'].': '.__('free shipping from'), 'rules' => ['nullable', 'numeric', 'min:0', 'max:100000'], 'help' => __('Leave empty for no free shipping.')];
            $zones[] = ['name' => "shipping_{$code}_days_min", 'path' => "shop.shipping.{$i}.delivery_days.0", 'type' => 'number', 'label' => $zone['code'].': '.__('delivery time from (working days)'), 'rules' => ['nullable', 'integer', 'min:0', 'max:90', "required_with:shipping_{$code}_days_max"]];
            $zones[] = ['name' => "shipping_{$code}_days_max", 'path' => "shop.shipping.{$i}.delivery_days.1", 'type' => 'number', 'label' => $zone['code'].': '.__('delivery time to (working days)'), 'rules' => ['nullable', 'integer', 'min:0', 'max:90', "required_with:shipping_{$code}_days_min", "gte:shipping_{$code}_days_min"]];
        }

        return [
            'seller' => [
                'label' => __('Seller'),
                'description' => __('The legal entity: printed on invoices and used in the imprint and the legal texts.'),
                'fields' => [
                    $text('seller_name', 'shop.seller.name', __('Name'), 150, true),
                    $text('seller_address1', 'shop.seller.address1', __('Street and number'), 150, true),
                    $text('seller_zip', 'shop.seller.zip', __('Postal code'), 20, true),
                    $text('seller_city', 'shop.seller.city', __('City'), 100, true),
                    ['name' => 'seller_email', 'path' => 'shop.seller.email', 'type' => 'email', 'label' => __('Email address'), 'rules' => ['required', 'email', 'max:255']],
                    $text('seller_phone', 'shop.seller.phone', __('Phone'), 50),
                    $text('seller_register', 'shop.seller.register', __('Register entry'), 150),
                    $text('seller_representatives', 'shop.seller.representatives', __('Represented by')),
                    $text('seller_vat_id', 'shop.seller.vat_id', __('VAT ID'), 20),
                    $text('seller_tax_number', 'shop.seller.tax_number', __('Tax number'), 30),
                    $text('seller_bank', 'shop.seller.bank', __('Bank'), 100),
                    $text('seller_iban', 'shop.seller.iban', 'IBAN', 34),
                    $text('seller_bic', 'shop.seller.bic', 'BIC', 11),
                ],
            ],
            'payment' => [
                'label' => __('Payment'),
                'description' => __('Prepayment by bank transfer is offered as soon as an IBAN is set under Seller. Online payment needs a Mollie key.'),
                'fields' => [
                    ['name' => 'mollie_key', 'path' => 'shop.payments.mollie.api_key', 'type' => 'secret', 'label' => __('Mollie API key'), 'rules' => ['nullable', 'string', 'max:100', 'regex:/^(live|test)_[A-Za-z0-9]+$/'], 'help' => __('Starts with live_ or test_. Leave empty to keep the saved key.')],
                    ['name' => 'invoice_due_days', 'path' => 'shop.invoice.due_days', 'type' => 'number', 'label' => __('Invoice due after (days)'), 'rules' => ['required', 'integer', 'min:0', 'max:90']],
                    ['name' => 'unpaid_cancel_days', 'path' => 'shop.orders.unpaid_cancel_days', 'type' => 'number', 'label' => __('Cancel unpaid orders after (days)'), 'rules' => ['required', 'integer', 'min:1', 'max:90']],
                ],
            ],
            'mail' => [
                'label' => __('Email'),
                'description' => __('How the shop sends order confirmations, invoices and login codes.'),
                'fields' => [
                    ['name' => 'mail_mailer', 'path' => 'mail.default', 'type' => 'select', 'label' => __('Delivery'), 'options' => ['smtp' => __('SMTP server'), 'log' => __('Do not send (write to the log)')], 'rules' => ['required', Rule::in(['smtp', 'log'])]],
                    $text('mail_host', 'mail.mailers.smtp.host', __('SMTP host')),
                    ['name' => 'mail_port', 'path' => 'mail.mailers.smtp.port', 'type' => 'number', 'label' => __('SMTP port'), 'rules' => ['nullable', 'integer', 'min:1', 'max:65535']],
                    ['name' => 'mail_scheme', 'path' => 'mail.mailers.smtp.scheme', 'type' => 'select', 'label' => __('Encryption'), 'options' => ['' => __('STARTTLS (port 587)'), 'smtps' => __('TLS (port 465)')], 'rules' => ['nullable', Rule::in(['smtps'])]],
                    $text('mail_username', 'mail.mailers.smtp.username', __('SMTP user')),
                    ['name' => 'mail_password', 'path' => 'mail.mailers.smtp.password', 'type' => 'secret', 'label' => __('SMTP password'), 'rules' => ['nullable', 'string', 'max:255'], 'help' => __('Leave empty to keep the saved password.')],
                    ['name' => 'mail_from_address', 'path' => 'mail.from.address', 'type' => 'email', 'label' => __('Sender address'), 'rules' => ['required', 'email', 'max:255']],
                    $text('mail_from_name', 'mail.from.name', __('Sender name'), 100, true),
                ],
            ],
            'shipping' => [
                'label' => __('Shipping'),
                'description' => __('Prices include VAT. Delivery times are shown to customers and are a promise.'),
                'fields' => [
                    ...$zones,
                    $text('carrier', 'shop.carrier', __('Carrier'), 100),
                ],
            ],
            'tax' => [
                'label' => __('Tax'),
                'description' => __('Ask your tax adviser before changing these.'),
                'fields' => [
                    ['name' => 'tax_small_business', 'path' => 'shop.tax.small_business', 'type' => 'bool', 'label' => __('Small business (§ 19 UStG): no VAT is charged'), 'rules' => ['boolean']],
                    ['name' => 'tax_oss_registered', 'path' => 'shop.tax.oss_registered', 'type' => 'bool', 'label' => __('Registered for the One-Stop-Shop: destination VAT for all EU consumers'), 'rules' => ['boolean']],
                ],
            ],
            'datev' => [
                'label' => __('DATEV export'),
                'description' => __('Numbers and accounts written into the DATEV export. The defaults follow SKR 04; let your tax adviser confirm them.'),
                'fields' => [
                    $text('datev_consultant_number', 'shop.datev.consultant_number', __('Consultant number'), 10),
                    $text('datev_client_number', 'shop.datev.client_number', __('Client number'), 10),
                    ['name' => 'datev_skr', 'path' => 'shop.datev.skr', 'type' => 'select', 'label' => __('Chart of accounts'), 'options' => ['03' => 'SKR 03', '04' => 'SKR 04'], 'rules' => ['required', Rule::in(['03', '04'])]],
                    $account('datev_debtor_account', 'shop.datev.debtor_account', __('Debtor account (customers)')),
                    $account('datev_account_19', 'shop.datev.accounts.domestic_19', __('Revenue account: Germany 19 %')),
                    $account('datev_account_7', 'shop.datev.accounts.domestic_7', __('Revenue account: Germany 7 %')),
                    $account('datev_account_0', 'shop.datev.accounts.domestic_0', __('Revenue account: Germany 0 %')),
                    $account('datev_account_ic', 'shop.datev.accounts.intra_community', __('Revenue account: EU businesses (tax free)')),
                    $account('datev_account_export', 'shop.datev.accounts.export', __('Revenue account: export (third countries)')),
                    $account('datev_account_oss', 'shop.datev.accounts.oss', __('Revenue account: EU consumers (One-Stop-Shop)')),
                    $account('datev_account_rc', 'shop.datev.accounts.reverse_charge', __('Revenue account: reverse charge')),
                    $account('datev_account_small', 'shop.datev.accounts.small_business', __('Revenue account: small business')),
                ],
            ],
            'appearance' => [
                'label' => __('Appearance'),
                'description' => __('What every theme uses. The design itself (colours, type, layout of shop, invoice and emails) comes from the theme, which is installed with the shop.'),
                'fields' => [
                    ['name' => 'logo', 'path' => 'shop.appearance.logo', 'type' => 'image', 'label' => __('Logo'), 'rules' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'], 'help' => __('PNG, JPG or WebP, up to 1 MB. Shown on invoices and in emails; whether the shop shows it is up to the theme.')],
                    ['name' => 'icon', 'path' => 'shop.appearance.icon', 'type' => 'image', 'label' => __('Icon'), 'rules' => ['nullable', 'image', 'mimes:png', 'dimensions:ratio=1/1,min_width=180', 'max:512'], 'help' => __('Square PNG, at least 180 pixels wide. Shown in the browser tab and on home screens.')],
                    ['name' => 'invoice_note', 'path' => 'shop.appearance.invoice_note', 'type' => 'textarea', 'label' => __('Note on every invoice'), 'rules' => ['nullable', 'string', 'max:1000']],
                    ['name' => 'mail_signature', 'path' => 'shop.appearance.mail_signature', 'type' => 'textarea', 'label' => __('Closing lines of every email'), 'rules' => ['nullable', 'string', 'max:500'], 'help' => __('Leave empty to sign with the seller\'s name.')],
                ],
            ],
            'channels' => [
                'label' => __('Channels and search engines'),
                'description' => __('Where else the books are offered, and who may read the shop.'),
                'fields' => [
                    ...array_map(fn (string $locale): array => ['name' => 'site_description_'.$locale, 'path' => 'shop.site.description.'.$locale, 'type' => 'textarea', 'label' => __('What the shop is, in one or two sentences (:language)', ['language' => strtoupper($locale)]), 'rules' => ['nullable', 'string', 'max:300'], 'help' => __('Shown in search results and told to AI assistants.')], config('shop.locales')),
                    ['name' => 'seo_indexable', 'path' => 'shop.seo.indexable', 'type' => 'bool', 'label' => __('Search engines may index the shop (switch off until the shop is ready)'), 'rules' => ['boolean']],
                    ['name' => 'seo_llms_txt', 'path' => 'shop.seo.llms_txt', 'type' => 'bool', 'label' => __('Publish the catalogue for AI assistants (llms.txt)'), 'rules' => ['boolean']],
                    ['name' => 'analytics_enabled', 'path' => 'shop.analytics.enabled', 'type' => 'bool', 'label' => __('Count visitors anonymously, without cookies'), 'rules' => ['boolean'], 'help' => __('No cookie, no stored address, no profile; visitors who send Do Not Track are not counted. Described in the privacy policy.')],
                    ['name' => 'google_feed', 'path' => 'shop.channels.google_feed', 'type' => 'bool', 'label' => __('Product feed for Google Merchant Center'), 'rules' => ['boolean'], 'help' => self::feedAddresses('google')],
                    ['name' => 'meta_feed', 'path' => 'shop.channels.meta_feed', 'type' => 'bool', 'label' => __('Product feed for Meta (Facebook and Instagram shops)'), 'rules' => ['boolean'], 'help' => self::feedAddresses('meta')],
                    ['name' => 'google_verification', 'path' => 'shop.channels.google_verification', 'type' => 'text', 'label' => __('Google verification code'), 'rules' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'], 'help' => __('From Google Search Console or Merchant Center: the content of the google-site-verification tag.')],
                    ['name' => 'meta_verification', 'path' => 'shop.channels.meta_verification', 'type' => 'text', 'label' => __('Meta domain verification code'), 'rules' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'], 'help' => __('From Meta Business settings: the content of the facebook-domain-verification tag.')],
                    ['name' => 'same_as', 'path' => 'shop.site.same_as', 'type' => 'lines', 'label' => __('Profiles and websites of the project (one address per line)'), 'rules' => ['nullable', 'string', 'max:2000'], 'help' => __('Told to search engines as belonging to this shop.')],
                ],
            ],
            'hosting' => [
                'label' => __('Hosting'),
                'description' => __('Named in the imprint and the privacy policy as the processor for hosting and email.'),
                'fields' => [
                    $text('processor_name', 'shop.processor.name', __('Company'), 150),
                    $text('processor_address', 'shop.processor.address', __('Address')),
                    $text('processor_location', 'shop.processor.location', __('Server location'), 100),
                    $text('processor_log_retention', 'shop.processor.log_retention', __('Server logs are deleted after'), 50),
                ],
            ],
        ];
    }

    /**
     * The feed addresses to paste into Merchant Center or Meta Commerce Manager.
     */
    private static function feedAddresses(string $channel): string
    {
        return __('Feed address:').' '.implode(' · ', array_map(
            // url(), not route(): this also runs while the application boots, before routes exist.
            fn (string $locale) => url("feeds/{$channel}-{$locale}.xml"),
            config('shop.locales'),
        ));
    }

    /**
     * Current values for the settings page. Secrets are reported as set or
     * not set, never with their value.
     *
     * @return array<string, mixed>
     */
    public static function values(string $group): array
    {
        $values = [];

        foreach (self::groups()[$group]['fields'] as $field) {
            $value = config($field['path']);

            $values[$field['name']] = match ($field['type']) {
                'secret' => (string) $value !== '',
                'money' => $value === null ? null : number_format($value / 100, 2, '.', ''),
                'bool' => (bool) $value,
                'lines' => implode("\n", (array) $value),
                'image' => Appearance::url($field['path']),
                default => $value,
            };
        }

        return $values;
    }

    /**
     * Store validated input of one group and apply it to the running config.
     *
     * @param  array<string, mixed>  $input
     */
    public static function save(string $group, array $input): void
    {
        foreach (self::groups()[$group]['fields'] as $field) {
            $value = $input[$field['name']] ?? null;

            if ($field['type'] === 'image') {
                self::saveImage($field['path'], $value, (bool) ($input[$field['name'].'_remove'] ?? false));

                continue;
            }

            if ($field['type'] === 'secret') {
                // An empty secret field means "keep what is saved".
                if ((string) $value === '') {
                    continue;
                }

                $stored = Crypt::encryptString((string) $value);
            } else {
                $stored = json_encode(match ($field['type']) {
                    'money' => $value === null || $value === '' ? null : (int) round(((float) $value) * 100),
                    'number' => $value === null || $value === '' ? null : (int) $value,
                    'bool' => (bool) $value,
                    'lines' => array_values(array_filter(array_map(trim(...), preg_split('/\R/', (string) $value) ?: []))),
                    default => $value === '' ? null : $value,
                });
            }

            Setting::query()->updateOrCreate(['key' => $field['path']], ['value' => $stored]);
        }

        Cache::forget(self::CACHE_KEY);
        self::apply();
    }

    /**
     * A new file replaces the old one; the remove flag deletes it; nothing sent keeps it.
     */
    private static function saveImage(string $path, mixed $file, bool $remove): void
    {
        if (! $file instanceof UploadedFile && ! $remove) {
            return;
        }

        $disk = Storage::disk(Appearance::DISK);
        $old = (string) config($path);

        if ($old !== '') {
            $disk->delete($old);
        }

        Setting::query()->updateOrCreate(['key' => $path], [
            'value' => json_encode($file instanceof UploadedFile ? $file->store('branding', Appearance::DISK) : null),
        ]);
    }

    /**
     * Write the saved settings over config(). Called on boot; quiet when the
     * table does not exist yet (fresh install, before the first migration).
     */
    public static function apply(): void
    {
        try {
            $saved = Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());
        } catch (QueryException) {
            return;
        }

        $types = [];

        foreach (self::groups() as $group) {
            foreach ($group['fields'] as $field) {
                $types[$field['path']] = $field['type'];
            }
        }

        foreach ($saved as $path => $value) {
            // A row for a field that no longer exists is ignored, not applied blindly.
            if (! isset($types[$path]) || $value === null) {
                continue;
            }

            config([$path => $types[$path] === 'secret' ? Crypt::decryptString($value) : json_decode($value, true)]);
        }

        // A delivery time with a missing end is no delivery time.
        config(['shop.shipping' => array_map(function (array $zone): array {
            if (in_array(null, $zone['delivery_days'] ?? [null], true)) {
                unset($zone['delivery_days']);
            }

            return $zone;
        }, config('shop.shipping'))]);
    }
}
