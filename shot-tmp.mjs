import { webkit, chromium } from 'playwright';
for (const [n, t] of [
    ['webkit', webkit],
    ['chromium', chromium],
]) {
    const b = await t.launch();
    const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
    await p.goto(
        process.argv[2] +
            '/en/products/dialectic-of-austerity-on-architecture-and-excess',
    );
    await p.waitForLoadState('networkidle');
    await p.mouse.wheel(0, 700);
    await p.waitForTimeout(300);
    await p.evaluate(() => window.scrollTo(0, 0));
    await p.locator('header > div > a').click();
    await p.waitForURL(/\/en$/);
    await p.waitForTimeout(1200);
    console.log(
        n,
        'scrollY after going home:',
        await p.evaluate(() => Math.round(window.scrollY)),
        '| focused:',
        await p.evaluate(
            () => document.activeElement?.id || document.activeElement?.tagName,
        ),
    );
    await b.close();
}
