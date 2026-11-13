# Raumladen

A shop for a small publisher selling books from Germany to customers anywhere:
catalogue, cart, checkout, payment, invoices, accounting export and the legal
plumbing a German online shop needs, in one Laravel application with a
server-rendered React storefront.

It is built for a seller that is a German legal entity (the defaults assume a
registered association, an e.V.) with a list of tens to a few hundred titles.
It is not a marketplace and not a general-purpose shop system.

## What it does

- **Storefront** in German and English, rendered on the server, accessible
  (WCAG 2.2 AA is tested in a real browser), with light, dark and system
  colour scheme, search as you type and pages per author.
- **Checkout** as a guest or with an account, address suggestions, payment
  through Mollie or by bank transfer in advance.
- **VAT** for domestic sales, EU consumers (distance-selling threshold and
  One-Stop-Shop), EU businesses with a checked VAT ID, exports and small
  businesses.
- **Invoices and credit notes** with gapless numbers as PDF, partial refunds,
  and a DATEV export for the tax adviser.
- **Legal requirements of German distance selling**: imprint, terms,
  withdrawal information with the model form, privacy policy, an electronic
  withdrawal function, confirmation of receipt, fixed book prices.
- **Backend** in German and English with roles, invitations instead of
  registration, and all shop settings (seller, payment, mail, shipping, tax,
  channels) in the browser instead of the environment.
- **Search engines and AI assistants**: structured data, sitemaps, product
  feeds for Google and Meta, `llms.txt`.
- **Visitor statistics** without cookies and without storing addresses.
- **Themes** for storefront, invoice and emails, installed as packages.

[What the shop does, in detail](docs/functionality.md)

## Requirements

- PHP 8.3 or newer with the usual Laravel extensions and `intl`
- Composer 2
- Node.js 22.18 or newer
- MySQL or MariaDB

## Quick start

```
git clone https://github.com/wrklst/raumladen.git
cd raumladen
mysql -e 'CREATE DATABASE raumladen'
composer setup            # installs, creates the environment file and app key, migrates, builds
php artisan db:seed       # legal pages, customer groups, three demo books, an administrator
composer dev              # serves the shop with hot reloading
```

Open the address `composer dev` prints. The storefront is at `/de` and `/en`,
the backend at `/login`. The seeded administrator is `admin@example.com` with
the password `password`: change it before anyone else can reach the shop.

Then open **Administration → Shop settings** in the backend. The first card
lists everything that is still missing before the shop may sell.

[Setup, deployment and going live](docs/setup.md)

## Documentation

|                                        |                                                                           |
| -------------------------------------- | ------------------------------------------------------------------------- |
| [Functionality](docs/functionality.md) | Storefront, checkout, tax, invoices, payments, backend, roles, statistics |
| [Setup](docs/setup.md)                 | Installation, environment, deployment, scheduler, going live              |
| [Routing](docs/routing.md)             | Every address of the shop and who may open it                             |
| [Themes](docs/themes.md)               | How the look of storefront, invoice and emails is replaced                |
| [Development](docs/development.md)     | Code layout, tests, quality checks, conventions                           |

## A word on the legal texts

The shop ships with German and English texts for imprint, terms, withdrawal
and privacy, and its tax logic follows German and EU rules as the authors
understand them. Neither is legal or tax advice. Have the texts and the tax
settings reviewed before you sell.

## Licence

[MIT](LICENSE). Copyright WL Artworld Solutions GmbH.
