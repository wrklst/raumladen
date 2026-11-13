<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\TaxClass;
use App\Enums\UserRole;
use App\Models\CustomerGroup;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
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
    }

    private function seedPages(): void
    {
        $pages = [
            'imprint' => [
                'title' => ['de' => 'Impressum', 'en' => 'Imprint'],
                'body' => [
                    'de' => "## Angaben gemäß § 5 DDG\n\n**:name**\n:address1\n:zip :city\n\nVereinsregister: :register\nVertreten durch den Vorstand: :representatives\n\nE-Mail: :email\n\nUmsatzsteuer-Identifikationsnummer gemäß § 27a UStG: :vat_id\n\n### Verbraucherstreitbeilegung\n\nWir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.",
                    'en' => "## Information according to § 5 DDG\n\n**:name**\n:address1\n:zip :city, Germany\n\nRegister of associations: :register\nRepresented by the board: :representatives\n\nEmail: :email\n\nVAT ID according to § 27a UStG: :vat_id",
                ],
            ],
            'terms' => [
                'title' => ['de' => 'AGB', 'en' => 'Terms and Conditions'],
                'body' => [
                    'de' => "## Allgemeine Geschäftsbedingungen\n\n### 1. Geltungsbereich\nDiese AGB gelten für alle Bestellungen über unseren Online-Shop durch Verbraucher und Unternehmer.\n\n### 2. Vertragsschluss\nDie Darstellung der Produkte im Shop stellt kein bindendes Angebot dar. Mit dem Klick auf „Zahlungspflichtig bestellen“ geben Sie ein verbindliches Angebot ab. Der Vertrag kommt mit unserer Bestellbestätigung per E-Mail zustande.\n\n### 3. Preise und Versandkosten\nAlle Preise verstehen sich inklusive der gesetzlichen Umsatzsteuer. Für Bücher gilt die Buchpreisbindung. Die Versandkosten werden im Warenkorb ausgewiesen.\n\n### 4. Zahlung\nDie Zahlung erfolgt über die im Checkout angebotenen Zahlungsarten.\n\n### 5. Eigentumsvorbehalt\nDie Ware bleibt bis zur vollständigen Bezahlung unser Eigentum.\n\n### 6. Gewährleistung\nEs gelten die gesetzlichen Mängelhaftungsrechte.",
                    'en' => "## Terms and Conditions\n\n### 1. Scope\nThese terms apply to all orders placed through our online shop.\n\n### 2. Conclusion of contract\nThe presentation of products is not a binding offer. By clicking “Place binding order” you submit a binding offer. The contract is concluded with our order confirmation by email.\n\n### 3. Prices and shipping\nAll prices include statutory VAT. Shipping costs are shown in the cart.\n\n### 4. Payment\nPayment is made via the methods offered at checkout.\n\n### 5. Retention of title\nGoods remain our property until paid in full.\n\n### 6. Warranty\nStatutory warranty rights apply.",
                ],
            ],
            'withdrawal' => [
                'title' => ['de' => 'Widerrufsbelehrung', 'en' => 'Right of Withdrawal'],
                'body' => [
                    'de' => "## Widerrufsbelehrung\n\n### Widerrufsrecht\nSie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen. Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag, an dem Sie oder ein von Ihnen benannter Dritter, der nicht der Beförderer ist, die Waren in Besitz genommen haben bzw. hat.\n\nUm Ihr Widerrufsrecht auszuüben, müssen Sie uns (:name, :address1, :zip :city, :email) mittels einer eindeutigen Erklärung (z. B. ein mit der Post versandter Brief oder E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren.\n\n### Folgen des Widerrufs\nWenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, einschließlich der Lieferkosten (mit Ausnahme der zusätzlichen Kosten, die sich daraus ergeben, dass Sie eine andere Art der Lieferung als die von uns angebotene, günstigste Standardlieferung gewählt haben), unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist.\n\nSie haben die Waren unverzüglich und in jedem Fall spätestens binnen vierzehn Tagen ab dem Tag, an dem Sie uns über den Widerruf dieses Vertrags unterrichten, an uns zurückzusenden. Sie tragen die unmittelbaren Kosten der Rücksendung der Waren.",
                    'en' => "## Right of withdrawal\n\nYou have the right to withdraw from this contract within fourteen days without giving any reason. The withdrawal period is fourteen days from the day on which you or a third party named by you takes possession of the goods.\n\nTo exercise the right of withdrawal, you must inform us (:name, :address1, :zip :city, :email) of your decision by an unequivocal statement (e.g. a letter sent by post or email).\n\n### Effects of withdrawal\nIf you withdraw from this contract, we shall reimburse all payments received from you, including delivery costs (except additional costs resulting from your choice of a delivery other than the least expensive standard delivery offered by us), without undue delay and no later than fourteen days from the day we receive your notice. You must return the goods without undue delay and no later than fourteen days from the day you notify us. You bear the direct cost of returning the goods.",
                ],
            ],
            'privacy' => [
                'title' => ['de' => 'Datenschutzerklärung', 'en' => 'Privacy Policy'],
                'body' => [
                    'de' => "## Datenschutzerklärung\n\n### Verantwortlicher\n:name, :address1, :zip :city, :email\n\n### Verarbeitung bei Bestellungen\nWir verarbeiten Ihre Bestell- und Adressdaten zur Vertragserfüllung (Art. 6 Abs. 1 lit. b DSGVO). Rechnungsdaten bewahren wir gemäß den handels- und steuerrechtlichen Aufbewahrungsfristen (§ 147 AO, § 257 HGB) zehn Jahre auf.\n\n### Zahlungsdienstleister\nZahlungen werden über Mollie B.V., Keizersgracht 126, 1015 CW Amsterdam, Niederlande, abgewickelt. Dabei werden die für die Zahlung erforderlichen Daten an Mollie übermittelt.\n\n### Login per E-Mail-Code\nFür den Kundenbereich senden wir Ihnen einen einmaligen Anmeldecode per E-Mail. Es werden keine Passwörter gespeichert.\n\n### Cookies\nWir verwenden ausschließlich technisch notwendige Cookies (Sitzung, Warenkorb, Spracheinstellung).\n\n### Ihre Rechte\nSie haben das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit und Widerspruch sowie ein Beschwerderecht bei einer Aufsichtsbehörde.",
                    'en' => "## Privacy Policy\n\n### Controller\n:name, :address1, :zip :city, :email\n\n### Processing of orders\nWe process your order and address data to fulfil the contract (Art. 6(1)(b) GDPR). Invoice data is retained for ten years under German commercial and tax law.\n\n### Payment provider\nPayments are processed by Mollie B.V., Keizersgracht 126, 1015 CW Amsterdam, Netherlands.\n\n### Email code login\nWe send a one-time code to your email address to sign in. No passwords are stored.\n\n### Cookies\nOnly technically necessary cookies are used (session, cart, language).\n\n### Your rights\nYou have the right to access, rectification, erasure, restriction, data portability and objection, and to lodge a complaint with a supervisory authority.",
                ],
            ],
            'shipping' => [
                'title' => ['de' => 'Versand & Zahlung', 'en' => 'Shipping & Payment'],
                'body' => [
                    'de' => "## Versand\n\n- Deutschland: 3,90 € (versandkostenfrei ab 50 €)\n- EU: 9,90 € (versandkostenfrei ab 100 €)\n- Übrige Länder: 19,90 €\n\nLieferzeit innerhalb Deutschlands 2–4 Werktage.\n\n## Zahlung\n\nKreditkarte, PayPal, SEPA-Lastschrift und weitere Zahlarten über Mollie sowie Vorkasse per Überweisung.",
                    'en' => "## Shipping\n\n- Germany: €3.90 (free from €50)\n- EU: €9.90 (free from €100)\n- Rest of world: €19.90\n\nDelivery within Germany takes 2–4 working days.\n\n## Payment\n\nCredit card, PayPal, SEPA direct debit and more via Mollie, or prepayment by bank transfer.",
                ],
            ],
        ];

        $seller = config('shop.seller');
        $replacements = collect($seller)->mapWithKeys(fn ($value, $key) => [':'.$key => (string) $value])->all();

        foreach ($pages as $slug => $page) {
            Page::query()->firstOrCreate(['slug' => $slug], [
                'title' => $page['title'],
                'body' => array_map(fn (string $body) => strtr($body, $replacements), $page['body']),
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
