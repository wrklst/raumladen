# Themes

A theme decides how the storefront, the invoice PDF and the emails look. The
backend is not themed.

The shop ships with one theme, `resources/themes/default`, which is used
when `SHOP_THEME` is empty. Any other theme is a directory of the same
layout, usually a Composer package in its own Git repository.

## What a theme can contain

Every part is optional.

| Path                                   | What it does                                                                 |
| -------------------------------------- | ---------------------------------------------------------------------------- |
| `theme.json`                           | `name`, and `provides`: what the theme already guarantees for every visitor. |
| `resources/css/theme.css`              | Colours, type and fonts of the storefront.                                   |
| `resources/js/...`                     | Replacements for the shop's pages, layout and components.                    |
| `resources/views/...`                  | Replacements for the shop's Blade views: invoice and emails.                 |
| `resources/views/vendor/mail/html/...` | The frame and the look of all emails.                                        |

### theme.json

```json
{
    "name": "Example",
    "provides": ["high-contrast", "no-motion"]
}
```

- `name` becomes the class on `<html>` of storefront pages: `theme-example`.
  The theme's CSS hangs on that class, so dialogs rendered outside the page
  body inherit it and the backend stays as it is.
- `provides` lists what the theme already does for everyone. The display
  settings then leave that switch out: a theme set in pure black and white
  lists `high-contrast`, a theme without animations and transitions lists
  `no-motion`.

### Styles

`resources/css/theme.css` is loaded after the shop's own CSS. The shop's
components read CSS variables; a theme sets them:

```css
html.theme-example:root {
    --background: #fff;
    --foreground: #000;
    --primary: #000;
    --primary-foreground: #fff;
    --muted: #fff;
    --muted-foreground: #000;
    --border: #000;
    --input: #000;
    --focus: #000;
    --radius-lg: 0;
    --radius-md: 0;
    --radius-sm: 0;
    --font-sans: 'Example Sans', ui-sans-serif, system-ui, sans-serif;
}

html.theme-example.dark:root {
    --background: #000;
    --foreground: #fff;
}
```

The full list of variables is at the top of `resources/css/app.css`. Fonts
go next to the CSS and are referenced with relative `url()`s; the build
copies them. Keep them on your own server: the storefront loads nothing from
third parties, and a test checks that.

Two rules matter for accessibility: text needs a contrast of 4.5 to 1
against its background, and form controls and the focus ring 3 to 1. The
browser tests run axe against whatever theme is selected.

### Pages, layout and components

A file under the theme's `resources/js` replaces the shop's file of the same
path:

| Theme file                                    | Replaces                                          |
| --------------------------------------------- | ------------------------------------------------- |
| `resources/js/pages/shop/home.tsx`            | The catalogue page.                               |
| `resources/js/pages/shop/product.tsx`         | The product page.                                 |
| `resources/js/layouts/shop-layout.tsx`        | Header, footer and frame of the storefront.       |
| `resources/js/components/shop/text-cover.tsx` | The cover set in type for books without an image. |

Inside a theme file, import shop code with `@/...` and other theme files
with `@theme/...` (which points at the theme's `resources` directory).

A replacement takes the place of the original entirely. It cannot import the
original and wrap it: an import of the shop's path resolves to the theme's
file again. Copy the shop's file as a starting point.

Tailwind classes work in theme files of packages named `raumladen-theme-*`
and of themes in a `themes` directory of the shop; the shop's stylesheet
scans those places.

### Invoice

| Theme file                                 | Replaces                                         |
| ------------------------------------------ | ------------------------------------------------ |
| `resources/views/invoices/style.blade.php` | The invoice's colours and type only. Start here. |
| `resources/views/invoices/pdf.blade.php`   | The whole invoice.                               |

The PDF is rendered by dompdf: CSS 2.1, no flexbox or grid, fonts as TrueType
files only. The built-in template uses DejaVu Sans, which covers umlauts and
the euro sign.

### Emails

| Theme file                                                                                     | Replaces                |
| ---------------------------------------------------------------------------------------------- | ----------------------- |
| `resources/views/vendor/mail/html/themes/default.css`                                          | The look of all emails. |
| `resources/views/vendor/mail/html/header.blade.php`, `footer.blade.php`, `button.blade.php`, … | Parts of the frame.     |
| `resources/views/mail/order-received.blade.php`, …                                             | Single emails.          |
| `resources/views/mail/signature.blade.php`                                                     | The closing lines.      |

## What is not part of a theme

Logo, icon, the note on every invoice and the closing lines of emails are
set in the backend under **Shop settings → Appearance**. Every theme uses
them: the invoice and the email header show the logo, the browser tab shows
the icon. The storefront receives the logo's address as `shop.logo`; the
built-in layout sets the shop's name in type instead.

## Installing a theme

```
composer require acme/raumladen-theme-example
```

Select it in the environment and build:

```
SHOP_THEME=acme/raumladen-theme-example
npm run build:ssr
```

`SHOP_THEME` is read twice: by the build (`vite.config.ts`), which compiles
the theme's styles and replacements into the front end, and by the
application (`App\Theme\Theme`), which lets the theme's views win. A package
name is looked up in `vendor/`; anything else is taken as a path.

Changing or updating a theme is therefore always a build and a deploy, and a
restart of the SSR process. Invoice and email views alone are picked up
without a build.

If the selected theme is not installed, the build and the application both
stop with a message that names it.

### Private themes

A theme for one shop usually lives in a private repository, while the shop
software is public. Keep the theme out of the shop's `composer.json` and add
it where the shop is installed.

Composer needs to know where the package is and how to authenticate:

```
composer config repositories.theme vcs https://github.com/acme/raumladen-theme-example
composer config --global github-oauth.github.com <token>
composer require acme/raumladen-theme-example:dev-main
```

The token is a personal access token with read access to that repository.
It is stored for the user on that machine, not in the project. In CI, pass
it in the `COMPOSER_AUTH` environment variable.

On a server that builds each release from the public repository, add the
theme in the deploy script after `composer install`:

```
$FORGE_COMPOSER config repositories.theme vcs https://github.com/acme/raumladen-theme-example
$FORGE_COMPOSER require acme/raumladen-theme-example:dev-main --no-interaction --update-no-dev
```

## Making a theme

1. Copy `resources/themes/default` into a new repository.
2. Add a `composer.json` with a package name ending in
   `raumladen-theme-<something>` and the type `raumladen-theme`.
3. Work on it against a shop checkout, without Composer:

    ```
    SHOP_THEME=../my-theme npm run dev
    ```

4. Run the shop's tests with the theme. They include the accessibility
   checks in a real browser, in light and dark:

    ```
    SHOP_THEME=../my-theme npm run build:ssr
    SHOP_THEME=../my-theme php artisan test
    ```
