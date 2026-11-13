# Setup

## Installation

```
composer setup
php artisan db:seed
```

`composer setup` installs the PHP and JavaScript dependencies, creates the
environment file from `.env.example` if there is none, generates the
application key, runs the migrations and builds the front end.

`php artisan db:seed` creates what a shop needs to start:

- the customer groups "b2c" (prices including VAT, the default) and "b2b"
  (net prices),
- the content pages: imprint, terms, withdrawal, privacy, shipping and
  payment, accessibility,
- three demo books,
- an administrator, `admin@example.com` with the password `password`, and a
  demo customer.

Seeding twice does not duplicate anything. Change the administrator's
password under **Settings → Security** before the shop is reachable by
others; there you can also add a second factor or a passkey.

## Environment

The environment holds only what the server needs to start. Everything a shop
owner decides is set in the backend.

| Variable                                            | Meaning                                                                                                                                              |
| --------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| `APP_NAME`, `VITE_APP_NAME`                         | The shop's public name. Used in page titles and metadata. `VITE_APP_NAME` is read when the front end is built.                                       |
| `APP_URL`                                           | The exact address of the shop, with `https://` and without a path. Links in emails, sitemaps and feeds use it, and passkeys only verify against it.  |
| `APP_ENV`, `APP_DEBUG`, `APP_KEY`                   | As in every Laravel application. The key also encrypts the payment key and the mail password stored in the database: keep it when you move the shop. |
| `DB_*`                                              | The database.                                                                                                                                        |
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | The database drivers work out of the box. The shop sends its mail directly, so no queue worker is needed.                                            |
| `SHOP_THEME`                                        | The theme: a package name, a path, or empty for the built-in theme. Read when the front end is built. See [Themes](themes.md).                       |
| `INERTIA_SSR_ENABLED`                               | `true` to render pages on the server (needs the SSR process, see below).                                                                             |

After changing the environment on a server, run `php artisan optimize` (or
deploy) so the cached configuration is rebuilt.

## Shop settings

**Administration → Shop settings** in the backend:

| Group                       | What you set                                                                                                                                 |
| --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| Seller                      | The legal entity: name, address, register, board, VAT ID or tax number, bank details. Printed on invoices and in the legal texts.            |
| Payment                     | Mollie API key, days until an invoice is due, days until an unpaid order is cancelled.                                                       |
| Email                       | SMTP server, sender, and a button that sends a test message.                                                                                 |
| Shipping                    | Price, free-shipping threshold and delivery time per zone, carrier.                                                                          |
| Tax                         | Small business, One-Stop-Shop registration.                                                                                                  |
| DATEV export                | Consultant and client number, chart of accounts, revenue accounts.                                                                           |
| Appearance                  | Logo, icon, a note on every invoice, the closing lines of emails.                                                                            |
| Channels and search engines | The shop's description, indexing on or off, `llms.txt`, visitor statistics, product feeds for Google and Meta, verification codes, profiles. |
| Hosting                     | Who hosts the shop and sends its mail, for imprint and privacy policy.                                                                       |

Secrets are stored encrypted and never sent back to the browser: an empty
field means "keep what is saved".

The first card on that page, **Before going live**, lists what is missing.
The same list on the command line:

```
php artisan shop:check
```

The legal pages contain placeholders such as `:name` or `:vat_id`. They are
filled from the settings whenever a page is shown, so the imprint always
states what is set. What is not set yet appears as `[TODO: …]`.

## Running in production

Three things run next to the web server.

**Build.** The front end is compiled, once for the browser and once for
server-side rendering:

```
npm ci
npm run build:ssr
```

**Server-side rendering.** A Node process renders pages for the first request
of a visitor and for crawlers:

```
php artisan inertia:start-ssr
```

Run it under a process supervisor and restart it after every deploy
(`php artisan inertia:stop-ssr` stops it; the supervisor starts the new
build). Without it the shop still works and renders in the browser.

**Scheduler.** Once a minute:

```
php artisan schedule:run
```

It cancels unpaid orders after the configured number of days, which returns
their stock, and deletes visitor statistics older than thirteen months.

### Deploy script

For a server managed with Laravel Forge and zero-downtime releases:

```
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci
npm run build:ssr

$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan optimize

$ACTIVATE_RELEASE()

$FORGE_PHP artisan inertia:stop-ssr || true
```

Notes:

- Migrate before `optimize`: the application reads its settings from the
  database when it boots.
- `|| true` on the last line: stopping fails when no SSR process is running,
  for example on the first deploy.
- The SSR process has to run in the `current` directory of the site, not in
  a release directory.
- Node needs its development dependencies for the build. If the server sets
  `NODE_ENV=production`, the build tool is not installed.
- A theme from a private repository is added in this script, see
  [Themes](themes.md#private-themes).

## Going live

1. Work through **Before going live** in the shop settings.
2. Have the legal texts (Catalogue → Pages) and the tax settings reviewed.
3. Enter a live Mollie key and real mail delivery; send the test message.
4. Replace the demo books and remove the demo customer.
5. Switch on **Search engines may index the shop** under Channels and search
   engines. While it is off, `robots.txt` and every page tell crawlers to
   stay away.
6. Place a test order with each payment method, confirm the payment, check
   the invoice PDF and the emails, cancel it and check the credit note.

## Moving or restoring a shop

Back up the database and `storage/app` (invoice PDFs, uploaded logo and
icon, product images). Keep `APP_KEY`: without it the stored payment key and
mail password cannot be decrypted and have to be entered again.
