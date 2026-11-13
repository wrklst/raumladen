# Routing

Every address of the shop, grouped by who uses it. Routes are defined in
`routes/web.php` (public, technical), `routes/shop.php` (storefront),
`routes/admin.php` (backend), `routes/settings.php` (a backend user's own
account) and, for the statistics beacon, in `bootstrap/app.php`.

`php artisan route:list --except-vendor` prints the current list.

## Languages

Storefront addresses start with the language: `/de/...`, `/en/...`. The
`SetLocale` middleware sets the language from that prefix, remembers it in
the session and makes it the default for generated links. It runs before
authentication and before route model binding, because guests are redirected
to the sign-in page of their language and books are looked up by the slug of
the language in the address.

`/` redirects to a language: last visited, then the browser's preference,
then the first entry of `shop.locales`.

Backend and sign-in addresses have no prefix. `SetBackendLocale` picks their
language from the user's saved choice, then the session, then the browser.
`POST /locale` changes it.

## Storefront

`{locale}` is `de` or `en`.

| Address                                | Purpose                                                                                            |
| -------------------------------------- | -------------------------------------------------------------------------------------------------- |
| `GET /{locale}`                        | Catalogue. `?q=` searches, `?author=` lists one author's books, `?page=` pages.                    |
| `GET /{locale}/products/{slug}`        | A book, by the slug of that language. Inactive and unpublished books are not found.                |
| `GET /{locale}/pages/{slug}`           | A content page (imprint, terms, …).                                                                |
| `GET /{locale}/cart`                   | The cart.                                                                                          |
| `POST /{locale}/cart`                  | Add a book.                                                                                        |
| `PATCH`, `DELETE /{locale}/cart/{id}`  | Change a quantity, remove a line.                                                                  |
| `POST`, `DELETE /{locale}/cart/coupon` | Apply or remove a coupon.                                                                          |
| `GET /{locale}/checkout`               | Checkout. `?country=` and `?vat_id=` recalculate the totals.                                       |
| `POST /{locale}/checkout`              | Place the order; redirects to the payment provider or the confirmation.                            |
| `GET /{locale}/orders/{uuid}/payment`  | Return from the payment provider; shows "payment not completed" if it failed.                      |
| `POST /{locale}/orders/{uuid}/payment` | Pay an unpaid order with another method.                                                           |
| `GET /{locale}/orders/{uuid}/complete` | Order confirmation.                                                                                |
| `GET`, `POST /{locale}/withdraw`       | Withdrawal form and its submission. `?order=` fills in the order number.                           |
| `GET /{locale}/address-suggestions`    | Address suggestions as JSON (`?q=`, `?country=`). Limited to 60 requests a minute.                 |
| `GET /{locale}/search-index`           | The catalogue as a search index for the browser. Not found when the catalogue is too large for it. |

An order page is open to the customer who owns the order, to backend users
who work with orders, and to the browser session that placed it.

## Customer account

| Address                                             | Purpose                                            |
| --------------------------------------------------- | -------------------------------------------------- |
| `GET /{locale}/account/login`                       | Sign-in.                                           |
| `POST /{locale}/account/login/code`                 | Send a code by email. Limited to 6 a minute.       |
| `POST /{locale}/account/login/verify`               | Check the code and sign in. Limited to 6 a minute. |
| `GET /{locale}/account`                             | The customer's orders.                             |
| `GET /{locale}/account/orders/{uuid}`               | One order.                                         |
| `GET /{locale}/account/orders/{uuid}/invoices/{id}` | An invoice or credit note as PDF.                  |
| `GET`, `PATCH /{locale}/account/profile`            | The profile.                                       |

## Backend

All under `/admin`, for signed-in backend users with a verified email. Each
group of routes belongs to an area; a role works in a set of areas
(`App\Enums\UserRole`). A request outside the user's areas is answered with 403.

| Address                                                                            | Area             | Purpose                                                   |
| ---------------------------------------------------------------------------------- | ---------------- | --------------------------------------------------------- |
| `/admin`                                                                           | any backend user | Overview.                                                 |
| `/admin/orders`, `/admin/orders/{uuid}`                                            | orders           | List and detail.                                          |
| `POST /admin/orders/{uuid}/ship`                                                   | shipping         | Mark as shipped; sends the shipping notice.               |
| `POST /admin/orders/{uuid}/confirm-payment`                                        | payments         | Confirm a bank transfer; issues the invoice.              |
| `POST /admin/orders/{uuid}/cancel`                                                 | payments         | Cancel; credit note and refund for paid orders.           |
| `POST /admin/orders/{uuid}/refund`                                                 | payments         | Refund items or shipping.                                 |
| `/admin/products…`                                                                 | catalog          | Books.                                                    |
| `/admin/pages…`                                                                    | content          | Content pages.                                            |
| `/admin/coupons…`                                                                  | coupons          | Coupons.                                                  |
| `/admin/customers…`, `/admin/customer-groups…`                                     | customers        | Customers and groups.                                     |
| `/admin/invoices`, `…/{id}/download`                                               | invoices         | Invoices and credit notes.                                |
| `/admin/datev`, `…/{id}/download`                                                  | datev            | DATEV exports.                                            |
| `/admin/statistics`                                                                | statistics       | Visitor statistics. `?days=` 7, 30, 90 or 365.            |
| `/admin/settings`, `PUT /admin/settings/{group}`, `POST /admin/settings/test-mail` | settings         | Shop settings.                                            |
| `/admin/staff…`                                                                    | settings         | Backend users: invite, change role, remove, invite again. |

A backend user's own account, for every role:

| Address                | Purpose                            |
| ---------------------- | ---------------------------------- |
| `/settings/profile`    | Name and email.                    |
| `/settings/security`   | Password, second factor, passkeys. |
| `/settings/appearance` | Colour scheme of the backend.      |

## Signing in to the backend

Provided by Laravel Fortify: `/login`, `/logout`, `/forgot-password`,
`/reset-password/{token}`, `/two-factor-challenge`, email verification and
the passkey routes. Registration is switched off.

| Address                          | Purpose                                                                                      |
| -------------------------------- | -------------------------------------------------------------------------------------------- |
| `GET`, `POST /invitation/{user}` | Accept an invitation and set a password. The address is signed and expires after seven days. |
| `GET /dashboard`                 | Sends a backend user to `/admin` and a customer to their account.                            |

Guests who open a backend address are sent to `/login`; guests who open an
account address are sent to the customer sign-in of their language.

## Technical addresses

| Address                                     | Purpose                                                                                                                                       |
| ------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET /robots.txt`                           | Generated. Keeps cart, checkout, account and backend out of search engines; blocks everything while indexing is switched off.                 |
| `GET /sitemap.xml`, `/sitemap-{locale}.xml` | Sitemaps with language alternates.                                                                                                            |
| `GET /llms.txt`                             | The catalogue as plain text for AI assistants.                                                                                                |
| `GET /feeds/{google                         | meta}-{locale}.xml`                                                                                                                           | Product feeds. Not found unless switched on in the settings. |
| `POST /webhooks/payments/{gateway}`         | Payment notifications. No CSRF token; the payment is fetched again from the provider, the request body is not trusted.                        |
| `POST /site-events`                         | The statistics beacon. Outside the web middleware: no session, no cookie, no CSRF token. Limited to 60 requests a minute. Always answers 204. |
| `GET /up`                                   | Health check.                                                                                                                                 |

## Calling routes from the front end

Addresses are not written by hand in TypeScript. Laravel Wayfinder generates
typed functions for controllers and named routes into
`resources/js/actions` and `resources/js/routes` when the front end is built:

```tsx
import CartController from '@/actions/App/Http/Controllers/Shop/CartController';
import { show as checkout } from '@/routes/shop/checkout';

<Form {...CartController.store.form()}>…</Form>
<Link href={checkout()}>…</Link>
```

The storefront layout sets the current language as the default for these
functions, so storefront links need no language argument.
