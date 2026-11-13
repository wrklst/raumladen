import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import fs from 'node:fs';
import path from 'node:path';
import { defineConfig, lazyPlugins, loadEnv } from 'vite-plus';

/*
 * The storefront's theme (see app/Theme/Theme.php): SHOP_THEME names an
 * installed theme package or a path; without it the built-in theme is used.
 * `@theme` points at the theme's resources, and any file a theme has under
 * resources/js replaces the shop's file of the same path.
 */
const themeName =
    process.env.SHOP_THEME ??
    loadEnv('production', process.cwd(), '').SHOP_THEME;
const themeDir =
    [`vendor/${themeName}`, `${themeName}`]
        .filter(() => Boolean(themeName))
        .map((candidate) => path.resolve(candidate))
        .find((candidate) => fs.existsSync(candidate)) ??
    path.resolve('resources/themes/default');

if (themeName && themeDir.endsWith('resources/themes/default')) {
    throw new Error(
        `Theme "${themeName}" is not installed: install its package (composer require) or correct SHOP_THEME.`,
    );
}

function themeOverrides() {
    const shopJs = `${path.resolve('resources/js')}/`;
    const themeJs = path.join(themeDir, 'resources/js');

    return {
        name: 'raumladen-theme',
        enforce: 'pre' as const,
        // The shop's module keeps its id (the Blade template and the build
        // manifest know pages by it) and hands over to the theme's file.
        load(this: { addWatchFile: (file: string) => void }, id: string) {
            const file = id.split('?')[0];

            if (!file.startsWith(shopJs)) {
                return null;
            }

            const override = path.join(themeJs, file.slice(shopJs.length));

            if (!fs.existsSync(override)) {
                return null;
            }

            this.addWatchFile(override);

            const hasDefault =
                /export\s+default\b|export\s*\{[^}]*\bdefault\b/.test(
                    fs.readFileSync(override, 'utf8'),
                );
            const target = JSON.stringify(override);

            return `export * from ${target};${hasDefault ? ` export { default } from ${target};` : ''}`;
        },
    };
}

export default defineConfig({
    resolve: {
        alias: { '@theme': path.join(themeDir, 'resources') },
    },
    plugins: lazyPlugins(() => [
        themeOverrides(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react(),
        babel({
            presets: [reactCompilerPreset()],
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ]),
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.{ts,tsx}'],
        setupFiles: ['resources/js/test-setup.ts'],
    },
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'bootstrap/ssr/**',
            'tailwind.config.js',
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            '.github/**',
            'composer.json',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
    },
});
