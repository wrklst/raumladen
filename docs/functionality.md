# Functionality

What the shop does, area by area. Where a rule comes from law, the text says
which; none of it is legal or tax advice.

## Storefront

- **Languages.** German and English, each under its own address prefix
  (`/de`, `/en`). `/` sends a visitor to the language of their last visit,
  then to the browser's preferred language, then to German. Languages are
  listed in `config/shop.php` (`locales`); UI texts live in `lang/<code>.json`
  with the English text as the key.
- **Catalogue.** All active, published books, newest first, 24 per page. A
  book without a cover image is shown with its title set in type.
- **Search.** Every word of the query has to match in the title (any
  language), the authors, the ISBN (with or without hyphens), the article
  number or the description; matching ignores case, accents and umlauts.
  Title, author and ISBN hits rank above description hits. Results appear
  while typing. Up to 300 visible books (`shop.search.browser_index_max`)
  the browser downloads the catalogue index once and searches locally; above
  that every search is answered by the server from a cached index.
- **Authors.** Each author on a book's page links to all books by that
  author.
- **Product page.** Price with the VAT note, delivery time, stock, book
  details, the manufacturer (product safety), and structured data for search
  engines.
- **Cart** in the session, with stock checks and one coupon.
- **Display settings.** Light, dark or system colour scheme, text size,
  underlined links, and higher contrast and reduced motion unless the theme
  already provides them. Stored in the visitor's browser only, and only
  once they choose.
- **Accessibility.** Skip link, focus moved to the new content on navigation
  and to the first invalid field after a failed submit, labelled field
  groups, announced totals, reflow at 320 pixels. Checked with axe in a real
  browser in light and dark, and with Lighthouse.

## Checkout

One page, no account needed.

- Email, billing address, optional different shipping address, payment
  method, order button labelled as required by § 312j BGB.
- **Address suggestions** for the street field. The browser asks the shop;
  the shop asks Photon (komoot, OpenStreetMap data) and caches every answer
  for 30 days, so neither the visitor's address nor their keystrokes reach a
  third party directly.
- Company, address line 2, VAT ID and a note are behind disclosures.
- No terms checkbox: the terms and the withdrawal and privacy information
  are linked directly above the order button.
- Stock is checked and reserved inside the order transaction.
- A **confirmation of receipt** is sent at once (§ 312i BGB) with the order,
  the bank details for prepayment, the terms and the withdrawal information.
- A guest order under an existing customer's email is attached to that
  account, but does not change the account's stored address.

## Customers

- **Sign-in without a password**: a six-digit code sent by email, valid for
  ten minutes. The first order creates the account.
- The account shows orders, invoices and credit notes as PDF, and a profile
  with addresses, VAT ID and language.
- **Customer groups** decide whether prices are shown gross or net and may
  carry their own price per book (for the trade).

## Prices and VAT

Prices are stored gross, including German VAT of the book's tax class. The
net price is derived from that and the rate of the destination applied.

| Sale                                                                | Treatment                                                                  |
| ------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| Germany                                                             | German rate.                                                               |
| EU consumer, seller below the EUR 10,000 distance-selling threshold | German rate.                                                               |
| EU consumer, threshold crossed or registered for the One-Stop-Shop  | Rate of the destination country.                                           |
| EU business with a VAT ID confirmed by VIES                         | Tax free intra-community supply; both VAT IDs and the note on the invoice. |
| Outside the EU                                                      | Tax free export.                                                           |
| Seller is a small business (§ 19 UStG)                              | No VAT at all.                                                             |

- The threshold is computed from the shop's own orders of the current and
  the previous year.
- A VAT ID is checked against VIES. A rejected ID blocks the order; if the
  service is unavailable the order goes through with VAT charged.
- Shipping is taxed at the rate of the order's largest line.
- The rate table for all EU countries is in `config/shop.php`
  (`tax.rates`). It is static: check it before going live and when a country
  changes its rates.
- **Fixed book prices.** Coupons do not apply to consumer orders shipped to
  the countries in `shop.fixed_price_countries` (Germany and Austria). Trade
  customers and other countries are not affected.

## Payment

- **Mollie** for cards, PayPal, direct debit and more. The customer is sent
  to Mollie and back; a webhook and the return page both update the order,
  and either may come first.
- **Bank transfer in advance.** Offered as soon as an IBAN is set. The order
  waits until someone with the payments role confirms the transfer.
- An unpaid order can be paid again with another method.
- Unpaid orders are cancelled after 14 days (configurable) and their stock
  returned.

## Invoices and accounting

- An **invoice** is issued when an order is paid: a PDF with a gapless
  number, stored unchangeable together with a snapshot of everything on it.
- **Cancellation** of a paid order issues a credit note for what is left,
  returns the stock and refunds through Mollie; bank transfers are refunded
  by hand.
- **Partial refunds**: single items, quantities or the shipping cost, each
  with its own credit note. The amounts of several partial credit notes add
  up exactly to the invoice.
- **DATEV export**: bookings as a DATEV-format file for a date range, with
  accounts per tax treatment, consultant and client number from the
  settings. Each invoice is exported once.

## Legal

- **Content pages** in Markdown: imprint, terms, withdrawal with the model
  form, privacy, shipping and payment, accessibility statement. Placeholders
  (`:name`, `:vat_id`, …) are filled from the shop settings when a page is
  shown.
- **Electronic withdrawal** (§ 356a BGB): "Withdraw from contract" in the
  footer of every page and on each order in the account. One form, one
  confirming button; the declaration is stored with its time and
  acknowledged by email to the customer and the seller.
- **Go-live check**: `php artisan shop:check` and the first card of the shop
  settings list what is missing.

## Backend

German and English; the language follows the user's choice, then the
session, then the browser.

| Menu           | Entries                                                              |
| -------------- | -------------------------------------------------------------------- |
| Overview       | Figures of the user's areas, recent orders.                          |
| Sales          | Orders (ship, confirm payment, cancel, refund), coupons, statistics. |
| Catalogue      | Products, content pages.                                             |
| Customers      | Customers, customer groups.                                          |
| Accounting     | Invoices, DATEV export.                                              |
| Administration | Shop settings, backend users.                                        |

### Roles

| Role          | Works in                                                                            |
| ------------- | ----------------------------------------------------------------------------------- |
| Administrator | Everything, including settings and backend users.                                   |
| Shop manager  | Orders, shipping, payments, books, pages, coupons, customers, invoices, statistics. |
| Fulfilment    | Sees orders and marks them as shipped.                                              |
| Editor        | Books and content pages.                                                            |
| Accounting    | Sees orders, confirms payments, cancels and refunds, invoices, DATEV export.        |

Menu, pages, the actions on an order and the figures on the overview follow
the role. The roles and their areas are defined in `App\Enums\UserRole`.

### Backend users

There is no registration. An administrator invites a person by email and
gives them a role; the link in the invitation is valid for seven days and
works once, and setting a password accepts it. The last administrator can
neither be removed nor given another role. Backend users sign in with their
password (and optionally a second factor or a passkey), never with the
customers' email code.

## Search engines and AI assistants

- Title, description, canonical address, language alternates and Open Graph
  data are in the HTML of every page, with and without server-side rendering.
- Structured data: organisation with return policy, website with search,
  books with offer, shipping and delivery time, breadcrumbs, lists.
- `sitemap.xml` with one sitemap per language, `robots.txt`, and `llms.txt`,
  a plain-text catalogue for AI assistants.
- **Product feeds** for Google Merchant Center and Meta, each switched on in
  the settings, where their addresses are shown.
- **Indexing switch**: while it is off, `robots.txt` blocks everything and
  every page carries `noindex`.

## Visitor statistics

Counted by the shop itself, without cookies and without consent banner.

- Nothing is stored in the browser.
- The server stores no address and no browser string. A visitor is counted
  through a key computed from both with a random value that changes every
  day and is discarded after 48 hours, so nobody is recognised across days.
- Stored: page, language, time on page, device class, the referring site's
  host, campaign tags. Order ids are removed from addresses; search text is
  never recorded.
- Visitors who send Do Not Track or Global Privacy Control are not counted.
- Data is deleted after thirteen months.
- **Sales → Statistics** shows visits, page views, time per visit, the way
  from visit to order, pages, sources, campaigns, devices and languages.

The privacy page describes this; whether it needs consent in your case is a
question for your lawyer.

## Emails

Confirmation of receipt, payment confirmation with the invoice attached,
shipping notice, sign-in code, withdrawal acknowledgement, invitation to the
backend. All in the language of the order or the user. Logo and closing
lines come from the settings; the look comes from the theme.
