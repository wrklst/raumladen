<?php

namespace App\Console\Commands;

use App\Enums\ProductType;
use App\Enums\TaxClass;
use App\Models\Author;
use App\Models\Product;
use Closure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        $folder = dirname((string) realpath($this->argument('file')));

        $validator = Validator::make(['products' => $entries], [
            'products' => ['required', 'array', 'list'],
            'products.*.sku' => ['required', 'string', 'max:64', 'distinct'],
            'products.*.title' => ['required', 'array'],
            'products.*.title.*' => ['required', 'string', 'max:255'],
            'products.*.slug.*' => ['string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'products.*.description.*' => ['nullable', 'string', 'max:20000'],
            'products.*.tax_class' => [Rule::enum(TaxClass::class)],
            'products.*.price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'products.*.cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'products.*.stock' => ['integer', 'min:0'],
            'products.*.track_stock' => ['boolean'],
            'products.*.weight_grams' => ['nullable', 'integer', 'min:0'],
            'products.*.specs.*' => ['nullable', 'string', 'max:5000'],
            'products.*.is_active' => ['boolean'],
            'products.*.published_at' => ['nullable', 'date'],
            'products.*.authors' => ['array', 'list'],
            'products.*.authors.*.name' => ['required', 'string', 'max:255'],
            'products.*.authors.*.bio.*' => ['nullable', 'string', 'max:5000'],
            'products.*.back_cover' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail) use ($folder): void {
                if (! in_array(strtolower(pathinfo((string) $value, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true) || @getimagesize($folder.'/'.$value) === false) {
                    $fail("{$value} is not a picture next to the catalogue file.");
                }
            }],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $created = 0;

        DB::transaction(function () use ($entries, $folder, &$created): void {
            foreach ($entries as $entry) {
                $attributes = Arr::only($entry, ['title', 'slug', 'description', 'tax_class', 'stock', 'track_stock', 'weight_grams', 'specs', 'is_active', 'published_at']);
                $attributes['price_cents'] = (int) round((float) $entry['price'] * 100);

                if (array_key_exists('cost', $entry)) {
                    $attributes['cost_cents'] = $entry['cost'] === null ? null : (int) round((float) $entry['cost'] * 100);
                }
                $attributes['slug'] = [...array_map(Str::slug(...), $entry['title']), ...$entry['slug'] ?? []];

                $product = Product::query()->firstOrNew(['sku' => $entry['sku']]);

                if (! $product->exists) {
                    $created++;
                    $product->fill(['type' => ProductType::Book, 'tax_class' => TaxClass::Reduced, 'stock' => 0, 'track_stock' => true, 'is_active' => true]);
                }

                if (array_key_exists('back_cover', $entry)) {
                    $attributes['back_cover'] = $this->storeBackCover($entry['back_cover'] === null ? null : $folder.'/'.$entry['back_cover'], $product->back_cover);
                }

                $product->fill($attributes)->save();

                // Authors named in the file become linked entries; a text about them is kept with the author.
                if (isset($entry['authors'])) {
                    $product->syncAuthorNames(array_column($entry['authors'], 'name'));

                    foreach ($entry['authors'] as $author) {
                        if (isset($author['bio'])) {
                            Author::named($author['name'])?->update(['bio' => $author['bio']]);
                        }
                    }
                } elseif (isset($entry['specs']['authors'])) {
                    $product->syncAuthorNames(explode(',', (string) $entry['specs']['authors']));
                }
            }
        });

        $updated = count($entries) - $created;
        $this->info("Created {$created} and updated {$updated} product(s).");

        return self::SUCCESS;
    }

    /**
     * Copies the picture into the public disk, named after its content, so a
     * changed picture gets a new address and an unchanged one is not copied again.
     */
    private function storeBackCover(?string $file, ?string $current): ?string
    {
        $disk = Storage::disk('public');
        $path = $file === null ? null : 'products/back-'.md5_file($file).'.'.strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if ($path !== null && ! $disk->exists($path)) {
            $disk->put($path, (string) file_get_contents($file));
        }

        if ($current !== null && $current !== $path) {
            $disk->delete($current);
        }

        return $path;
    }
}
