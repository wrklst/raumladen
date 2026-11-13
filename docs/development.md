# Development

## Stack

- Laravel 13 on PHP 8.3 or newer, Fortify for backend sign-in
- Inertia 3 with React 19 and TypeScript, rendered on the server
- Tailwind CSS 4, Radix UI primitives
- Pest for PHP tests including browser tests (Playwright), Vitest for
  front-end tests, Lighthouse CI
- Vite+ (`vp`) for building, linting and formatting the front end; Pint and
  PHPStan (Larastan) for PHP

## Running it

```
composer dev
```

starts the PHP server and the Vite dev server with hot reloading and
server-side rendering.

## Where things are

| Path                                           | What is there                                                                           |
| ---------------------------------------------- | --------------------------------------------------------------------------------------- |
| `app/Cart`                                     | The session cart and the calculator that turns it into lines, tax and totals.           |
| `app/Tax`                                      | Which VAT treatment applies, rounding, the VAT ID check.                                |
| `app/Checkout`, `app/Orders`                   | Placing an order, marking it paid, cancelling it.                                       |
| `app/Payments`                                 | Payment gateways behind one interface, and the manager that offers the available ones.  |
| `app/Invoicing`                                | Issuing invoices and credit notes, rendering the PDF.                                   |
| `app/Datev`                                    | The accounting export.                                                                  |
| `app/Settings`                                 | Shop settings stored in the database, the go-live check, placeholders of content pages. |
| `app/Theme`                                    | Resolving and registering the theme.                                                    |
| `app/Shop`                                     | Catalogue search, the current customer's pricing context.                               |
| `app/Analytics`                                | The anonymous visitor statistics.                                                       |
| `app/Address`                                  | Address suggestions.                                                                    |
| `app/Seo`                                      | Page metadata and structured data.                                                      |
| `app/Enums/UserRole.php`                       | Roles and the backend areas each role works in.                                         |
| `config/shop.php`                              | The defaults of everything shop-specific, with the reasons.                             |
| `resources/js/pages/shop`, `account`           | Storefront pages.                                                                       |
| `resources/js/pages/admin`, `auth`, `settings` | Backend pages.                                                                          |
| `resources/js/layouts/shop-layout.tsx`         | The storefront frame.                                                                   |
| `resources/themes/default`                     | The built-in theme.                                                                     |
| `database/seeders/pages`                       | The legal texts as Markdown, German and English.                                        |
| `lang/de.json`                                 | German UI texts; the English text is the key.                                           |

### How settings reach the code

Code reads `config('shop…')` and `config('mail…')` and never knows where a
value came from. `App\Settings\ShopSettings` defines which config paths can
be set in the backend, stores them in the `settings` table and writes them
over the config when the application boots. To make another value editable,
add a field to a group in `ShopSettings::groups()`.

### Adding a payment method

Implement `App\Payments\PaymentGateway` and register the class under a key
in `config/shop.php` (`payments.gateways`). The checkout offers every
gateway whose `available()` returns true.

### Adding a language

Add the code to `locales` in `config/shop.php`, create `lang/<code>.json`
and the Laravel message files under `lang/<code>/`, and add the legal texts
as `database/seeders/pages/<slug>.<code>.md`.

## Tests and checks

| Command                          | What it runs                                      |
| -------------------------------- | ------------------------------------------------- |
| `php artisan test`               | All PHP tests: unit, feature and browser.         |
| `php artisan test tests/Feature` | Without the browser tests.                        |
| `npm test`                       | Front-end tests (Vitest, jsdom).                  |
| `npm run check`                  | Formatting and linting of the front end.          |
| `npm run types:check`            | TypeScript.                                       |
| `vendor/bin/pint`                | PHP formatting.                                   |
| `vendor/bin/phpstan analyse`     | Static analysis.                                  |
| `npm run lighthouse`             | Builds and audits the storefront with Lighthouse. |
| `composer ci:check`              | What CI runs.                                     |

Notes:

- PHP tests use an in-memory SQLite database; no setup needed.
- **Browser tests need a build** (`npm run build:ssr`) and Playwright's
  browser (`npx playwright install chromium`). They always test the built
  assets, also while a dev server is running. The server-side rendering
  tests start their own SSR process and are skipped without the SSR bundle.
- Browser tests check every storefront page with axe in light and dark
  mode, keyboard use, a complete order, and that the browser hydrates the
  server-rendered markup without a mismatch.
- Lighthouse runs against a throwaway SQLite database it creates itself and
  never touches the configured one. It asserts 100 for accessibility and
  SEO on indexable pages.
- No test calls an outside service. VIES, Photon and Mollie are faked.
- A guard test fails when a text used in the backend has no German
  translation.

## Conventions

- Money is an integer number of cents everywhere.
- Controllers are thin; rules live in the classes listed above.
- A shortcut taken on purpose is marked with a `ponytail:` comment that
  names its limit and what to do when the limit is reached.
- Comments say why, not what.
- Front-end addresses come from Wayfinder functions, never from strings.
- Every user-visible text goes through `__()` or `t()`.
