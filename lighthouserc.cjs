// Lighthouse CI: `npm run lighthouse` builds the client and SSR bundles, then
// audits the storefront served like production (SSR on, debug off) from a
// throwaway seeded SQLite database. The configured database is never touched.
const path = require('node:path');

const port = 8123;
const database = path.resolve(
    __dirname,
    'storage/framework/testing/lighthouse.sqlite',
);
const base = `http://127.0.0.1:${port}`;

Object.assign(process.env, {
    APP_ENV: 'production',
    APP_DEBUG: 'false',
    APP_URL: base,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
    INERTIA_SSR_ENABLED: 'true',
});

module.exports = {
    ci: {
        collect: {
            // --no-reload: otherwise `artisan serve` strips the env above from its workers.
            startServerCommand: `rm -f ${database} && touch ${database} && php artisan migrate --seed --force && (php artisan inertia:start-ssr & php artisan serve --port=${port} --no-reload)`,
            startServerReadyPattern: 'Server running',
            startServerReadyTimeout: 60000,
            url: [
                `${base}/de`,
                `${base}/de/products/die-kunst-des-langsamen-lesens`,
                `${base}/en/products/the-mourning-of-space`,
                `${base}/de/pages/imprint`,
                `${base}/de/cart`,
                `${base}/de/withdraw`,
                `${base}/de?q=space`,
            ],
            numberOfRuns: 1,
        },
        assert: {
            assertMatrix: [
                {
                    matchingUrlPattern: '.*',
                    assertions: {
                        'categories:performance': ['error', { minScore: 0.9 }],
                        'categories:accessibility': ['error', { minScore: 1 }],
                        'categories:best-practices': [
                            'error',
                            { minScore: 0.95 },
                        ],
                    },
                },
                {
                    // Cart, withdrawal form and search results are noindex on purpose, which Lighthouse counts against SEO.
                    matchingUrlPattern: '^(?!.*(/cart|/withdraw|\\?q=)).*$',
                    assertions: {
                        'categories:seo': ['error', { minScore: 1 }],
                    },
                },
            ],
        },
        upload: {
            target: 'filesystem',
            outputDir: 'storage/logs/lighthouse',
        },
    },
};
