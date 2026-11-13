# Default theme

The theme Raumladen ships with, and the starting point for your own. It is
used whenever `SHOP_THEME` is empty.

A theme decides how the storefront, the invoice PDF and the emails look. It
is a directory with this layout; every part is optional:

| Path                                   | What it does                                                                                                                                                                                                                                                                                                                                          |
| -------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `theme.json`                           | `name` (the class on `<html>` becomes `theme-<name>`) and `provides`: what the theme already guarantees for every visitor (`high-contrast`, `no-motion`), so the shop does not offer it as a display option.                                                                                                                                          |
| `resources/css/theme.css`              | Colours, type and fonts of the storefront. Loaded after the shop's own CSS. Set the shop's CSS variables on `html.theme-<name>:root` and, for the dark scheme, on `html.theme-<name>.dark:root`. Fonts go next to it and are referenced with relative `url()`s.                                                                                       |
| `resources/js/...`                     | Any file here replaces the shop's file of the same path under `resources/js`: a page (`pages/shop/home.tsx`), the layout (`layouts/shop-layout.tsx`), a component (`components/shop/text-cover.tsx`). Import shop code with `@/...` and theme files with `@theme/...`. A replacement takes the place of the original; it cannot import and extend it. |
| `resources/views/...`                  | Blade views that replace the shop's: `invoices/style.blade.php` (the invoice's colours and type), `invoices/pdf.blade.php` (the whole invoice), `mail/*.blade.php` (single emails), `mail/signature.blade.php`.                                                                                                                                       |
| `resources/views/vendor/mail/html/...` | The frame of all emails: `themes/default.css` for the look, `header.blade.php`, `footer.blade.php`, `button.blade.php` for its parts.                                                                                                                                                                                                                 |

Logo, icon, the note on invoices and the closing lines of emails are not part
of a theme. They are set in the backend under Shop settings, Appearance, and
every theme uses them.

## Making a theme

1. Copy this directory into a repository of its own and give it a
   `composer.json` with a package name of the form `<vendor>/raumladen-theme-<name>`
   (name the package `raumladen-theme-*` so the shop finds its components
   when it compiles the styles).
2. Install it in the shop with Composer and select it:

    ```
    composer require <vendor>/raumladen-theme-<name>
    SHOP_THEME=<vendor>/raumladen-theme-<name>
    ```

    `SHOP_THEME` also accepts a path, which is handy while working on a theme:
    `SHOP_THEME=../my-theme`.

3. Build and deploy: `npm run build:ssr`. The storefront is compiled with its
   theme, so changing or updating a theme is always a build and a deploy.
   Invoice and email views are picked up without a build.

The tests run with whatever theme is selected, including the accessibility
checks in a real browser: `php artisan test`.
