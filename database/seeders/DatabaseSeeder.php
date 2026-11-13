<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\TaxClass;
use App\Enums\UserRole;
use App\Models\CustomerGroup;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use App\Shop\ProductSearch;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $b2c = CustomerGroup::query()->firstOrCreate(['code' => 'b2c'], [
            'name' => 'Privatkunden',
            'prices_include_tax' => true,
            'is_default' => true,
        ]);

        $b2b = CustomerGroup::query()->firstOrCreate(['code' => 'b2b'], [
            'name' => 'Händler / B2B',
            'prices_include_tax' => false,
            'is_default' => false,
        ]);

        User::query()->firstOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin',
            'password' => 'password',
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        User::query()->firstOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'role' => UserRole::Customer,
            'customer_group_id' => $b2c->id,
            'email_verified_at' => now(),
        ]);

        $this->seedPages();
        $this->seedProducts($b2b);

        // Model events are off while seeding, so the search index is not told about the new products.
        ProductSearch::forget();
    }

    /**
     * Legal and information pages from database/seeders/pages/{slug}.{locale}.md.
     * The texts keep their placeholders (:name, :vat_id, ...); they are filled
     * from the shop settings whenever a page is shown (App\Settings\PageVariables).
     */
    private function seedPages(): void
    {
        $titles = [
            'imprint' => ['de' => 'Impressum', 'en' => 'Imprint'],
            'terms' => ['de' => 'AGB', 'en' => 'Terms and Conditions'],
            'withdrawal' => ['de' => 'Widerrufsbelehrung', 'en' => 'Right of Withdrawal'],
            'privacy' => ['de' => 'Datenschutzerklärung', 'en' => 'Privacy Policy'],
            'shipping' => ['de' => 'Versand & Zahlung', 'en' => 'Shipping & Payment'],
            'accessibility' => ['de' => 'Barrierefreiheit', 'en' => 'Accessibility'],
        ];

        foreach ($titles as $slug => $title) {
            Page::query()->firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'body' => collect($title)
                    ->map(fn (string $_, string $locale) => File::get(__DIR__."/pages/{$slug}.{$locale}.md"))
                    ->all(),
                'is_published' => true,
            ]);
        }
    }

    private function seedProducts(CustomerGroup $b2b): void
    {
        if (Product::query()->exists()) {
            return;
        }

        $books = [
            [
                'sku' => 'BK-0001',
                'title' => ['de' => 'Die Kunst des langsamen Lesens', 'en' => 'The Art of Slow Reading'],
                'description' => [
                    'de' => 'Ein Essay über Aufmerksamkeit, Muße und das Buch als Gegenstand. Mit zahlreichen Abbildungen.',
                    'en' => 'An essay on attention, leisure and the book as an object. Richly illustrated.',
                ],
                'price_cents' => 2400,
                'specs' => ['isbn' => '9783000000011', 'authors' => 'Lena Hartmann', 'pages' => '192', 'binding' => 'Hardcover', 'language' => 'de', 'published' => '2025-03-01'],
                'weight_grams' => 420,
            ],
            [
                'sku' => 'BK-0002',
                'title' => ['de' => 'Stadt, Land, Fluss – Essays zur Landschaft', 'en' => 'City, Country, River – Essays on Landscape'],
                'description' => [
                    'de' => 'Zwölf Autorinnen und Autoren schreiben über Orte, die sie geprägt haben.',
                    'en' => 'Twelve writers on the places that shaped them.',
                ],
                'price_cents' => 1800,
                'specs' => ['isbn' => '9783000000028', 'authors' => 'Hrsg. Jonas Weber', 'pages' => '240', 'binding' => 'Paperback', 'language' => 'de', 'published' => '2024-10-15'],
                'weight_grams' => 350,
            ],
            [
                'sku' => 'BK-0003',
                'title' => ['de' => 'Typografie für Vereine', 'en' => 'Typography for Associations'],
                'description' => [
                    'de' => 'Ein praktisches Handbuch für Gestaltung von Mitgliederzeitschriften, Plakaten und Websites.',
                    'en' => 'A practical handbook for designing member magazines, posters and websites.',
                ],
                'price_cents' => 3200,
                'specs' => ['isbn' => '9783000000035', 'authors' => 'Mara Klein, Tim Vogt', 'pages' => '160', 'binding' => 'Paperback', 'language' => 'de', 'published' => '2025-06-20'],
                'weight_grams' => 480,
            ],
        ];

        foreach ($books as $book) {
            $product = Product::query()->create([
                'type' => ProductType::Book,
                'sku' => $book['sku'],
                'slug' => array_map(fn (string $t) => Str::slug($t), $book['title']),
                'title' => $book['title'],
                'description' => $book['description'],
                'tax_class' => TaxClass::Reduced,
                'price_cents' => $book['price_cents'],
                'stock' => 20,
                'track_stock' => true,
                'weight_grams' => $book['weight_grams'],
                'specs' => $book['specs'],
                'is_active' => true,
                'published_at' => now()->subDay(),
            ]);

            // Trade price for B2B: 30 % off list price.
            $product->prices()->create([
                'customer_group_id' => $b2b->id,
                'price_cents' => (int) round($book['price_cents'] * 0.7),
            ]);
        }
    }
}
