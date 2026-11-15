<?php

namespace App\Console\Commands;

use App\Enums\ProductType;
use App\Enums\TaxClass;
use App\Models\Product;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use JsonException;

/**
 * Loads a catalogue from a JSON file: a list of products, each identified by
 * its article number ("sku"). A product that exists is updated with the keys
 * its entry carries and keeps everything else, so an entry without "stock"
 * never overwrites the stock of a running shop. Nothing is written unless
 * every entry is valid.
 */
#[Signature('shop:import-products {file : JSON file with a list of products}')]
#[Description('Create or update products from a JSON file, matched by article number')]
class ImportProducts extends Command
{
    public function handle(): int
    {
        try {
            $entries = json_decode((string) @file_get_contents($this->argument('file')), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error("Cannot read {$this->argument('file')}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $validator = Validator::make(['products' => $entries], [
            'products' => ['required', 'array', 'list'],
            'products.*.sku' => ['required', 'string', 'max:64', 'distinct'],
            'products.*.title' => ['required', 'array'],
            'products.*.title.*' => ['required', 'string', 'max:255'],
            'products.*.slug.*' => ['string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'products.*.description.*' => ['nullable', 'string', 'max:20000'],
            'products.*.tax_class' => [Rule::enum(TaxClass::class)],
            'products.*.price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'products.*.stock' => ['integer', 'min:0'],
            'products.*.track_stock' => ['boolean'],
            'products.*.weight_grams' => ['nullable', 'integer', 'min:0'],
            'products.*.specs.*' => ['nullable', 'string', 'max:500'],
            'products.*.is_active' => ['boolean'],
            'products.*.published_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $created = 0;

        DB::transaction(function () use ($entries, &$created): void {
            foreach ($entries as $entry) {
                $attributes = collect($entry)
                    ->only(['title', 'slug', 'description', 'tax_class', 'stock', 'track_stock', 'weight_grams', 'specs', 'is_active', 'published_at'])
                    ->all();
                $attributes['price_cents'] = (int) round((float) $entry['price'] * 100);
                $attributes['slug'] = [...array_map(Str::slug(...), $entry['title']), ...$entry['slug'] ?? []];

                $product = Product::query()->firstOrNew(['sku' => $entry['sku']]);

                if (! $product->exists) {
                    $created++;
                    $product->fill(['type' => ProductType::Book, 'tax_class' => TaxClass::Reduced, 'stock' => 0, 'track_stock' => true, 'is_active' => true]);
                }

                $product->fill($attributes)->save();
            }
        });

        $updated = count($entries) - $created;
        $this->info("Created {$created} and updated {$updated} product(s).");

        return self::SUCCESS;
    }
}
