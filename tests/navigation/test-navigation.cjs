/* Real-template browser checks over static synthetic fixtures: no DB/auth writes. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const axeSource = fs.readFileSync(process.env.AXE_SOURCE || require.resolve('axe-core/axe.min.js'), 'utf8');
const { server, widths } = require('./capture.cjs');
const root = path.resolve(process.argv[2]);
const before = JSON.parse(fs.readFileSync(path.join(root, '..', 'before', 'capture-results.json')));
const results = [];
let checks = 0;
function check(condition, message) { checks++; assert.ok(condition, message); }
async function audit(page, name) {
  // Evaluate audit engine through DevTools, without weakening the page CSP.
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Runtime.evaluate', { expression: axeSource });
  const { result } = await cdp.send('Runtime.evaluate', {
    expression: `axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } })`,
    awaitPromise: true, returnByValue: true,
  });
  await cdp.detach();
  const violations = result.value.violations.map(({ id, impact, nodes }) => ({ id, impact, targets: nodes.map(n => n.target) }));
  fs.writeFileSync(path.join(root, `${name}-axe.json`), JSON.stringify(violations, null, 2));
  check(violations.length === 0, `${name}: axe violations: ${JSON.stringify(violations)}`);
}
async function main() {
  await new Promise(resolve => server.listen(8837, '127.0.0.1', resolve));
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const consoleErrors = [], requestErrors = [], pageErrors = [];
  async function pageFor(width, reducedMotion = 'reduce', height = 960, javaScriptEnabled = true) {
    const context = await browser.newContext({ viewport: { width, height }, reducedMotion, javaScriptEnabled });
    const page = await context.newPage();
    page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
    page.on('pageerror', error => pageErrors.push(error.message));
    page.on('response', response => { if (response.status() >= 400) requestErrors.push(`${response.status()} ${response.url()}`); });
    page.on('requestfailed', request => requestErrors.push(`${request.failure()?.errorText} ${request.url()}`));
    return { page, context };
  }
  try {
    for (const width of widths) {
      const { page, context } = await pageFor(width);
      for (const kind of ['public', 'admin']) {
        const url = `http://127.0.0.1:8837/axa-kenmark/${kind === 'admin' ? 'admin/?r=dashboard' : ''}`;
        await page.goto(url, { waitUntil: 'networkidle' });
        await page.waitForFunction(() => !document.getElementById('axa-loader'));
        const nav = page.locator('[data-navigation]');
        const toggle = nav.locator('[data-nav-toggle]');
        const panel = nav.locator('[data-nav-panel]');
        const metrics = await page.evaluate(() => {
          const root = document.querySelector('[data-navigation]');
          const header = root.querySelector('[data-nav-header]');
          const brand = header.querySelector('a');
          const toggle = root.querySelector('[data-nav-toggle]');
          return { overflow: document.documentElement.scrollWidth - innerWidth,
            height: header.getBoundingClientRect().height,
            overlap: brand.getBoundingClientRect().right > toggle.getBoundingClientRect().left,
            closedPanelInert: root.querySelector('[data-nav-panel]').inert,
          };
        });
        check(metrics.overflow === 0, `${kind}-${width}: closed overflow`);
        if (width <= 900) {
          check(metrics.height === 72, `${kind}-${width}: compact header`);
          check(!metrics.overlap, `${kind}-${width}: branding does not overlap toggle`);
          check(metrics.closedPanelInert, `${kind}-${width}: closed menu inert`);
          const contentBefore = await page.locator(kind === 'admin' ? '.admin-topbar' : '.hero').boundingBox();
          await toggle.click();
          check(await toggle.getAttribute('aria-expanded') === 'true', `${kind}-${width}: expanded state`);
          check(await panel.evaluate(el => !el.inert), `${kind}-${width}: open menu accessible`);
          check(await panel.evaluate(el => el.contains(document.activeElement)), `${kind}-${width}: focus enters panel`);
          const contentAfter = await page.locator(kind === 'admin' ? '.admin-topbar' : '.hero').boundingBox();
          check(contentBefore.y === contentAfter.y, `${kind}-${width}: no opening layout shift`);
          check(await page.locator('body').evaluate(el => el.classList.contains('nav-scroll-locked')), `${kind}-${width}: scroll locked`);
          check(await page.evaluate(() => document.documentElement.scrollWidth === innerWidth), `${kind}-${width}: open overflow`);
          const targets = await panel.locator('a, button').evaluateAll(elements => elements.filter(el => el.getClientRects().length).map(el => el.getBoundingClientRect().height));
          check(targets.every(height => height >= 44), `${kind}-${width}: 44px touch targets`);
          if (kind === 'admin') {
            const links = await panel.locator('.admin-nav a').evaluateAll(els => els.map(a => a.getAttribute('href')));
            const original = before.find(row => row.width === width && row.kind === 'admin').adminLinks.map(a => a.href);
            assert.deepEqual(links, original); checks++;
            check(await panel.locator('.admin-nav-count').textContent() === '7', `${width}: unread count retained`);
            check(await panel.locator('form').getAttribute('method') === 'post', `${width}: logout remains POST`);
            check(await panel.locator('input[name="_csrf"]').count() === 1, `${width}: CSRF field preserved`);
            check(await panel.locator('[aria-current="page"]').count() === 1, `${width}: current page`);
          } else {
            const links = await panel.locator('a').evaluateAll(els => els.map(a => a.href));
            check(links.length === 6, `${width}: all public links/CTA`);
            check(await page.evaluate(links => links.every(href => !!document.getElementById(new URL(href).hash.slice(1))), links), `${width}: every public anchor resolves`);
            check(await panel.locator('[aria-current="location"]').count() === 1, `${width}: current section`);
          }
          await page.screenshot({ path: path.join(root, 'screenshots', `${kind}-${width}-open.png`) });
          await audit(page, `${kind}-${width}-open`);
          // Shift+Tab from first root control wraps to final menu control.
          await nav.locator('[data-nav-header] a').first().focus();
          await page.keyboard.press('Shift+Tab');
          check(await panel.evaluate(el => el.contains(document.activeElement)), `${kind}-${width}: focus trap backwards`);
          await page.keyboard.press('Tab');
          check(await nav.locator('[data-nav-header] a').first().evaluate(el => el === document.activeElement), `${kind}-${width}: focus trap forwards`);
          await page.keyboard.press('Escape');
          check(await toggle.evaluate(el => el === document.activeElement), `${kind}-${width}: Escape restores focus`);
          check(await toggle.getAttribute('aria-expanded') === 'false', `${kind}-${width}: Escape closes`);
          check(await page.locator('body').evaluate(el => !el.classList.contains('nav-scroll-locked') && !el.inert), `${kind}-${width}: background restored`);
          await toggle.click();
          const backdrop = nav.locator('[data-nav-backdrop]');
          const backdropBox = await backdrop.boundingBox();
          // Real hit-tested click in the exposed backdrop: verifies stacking,
          // rather than dispatching a synthetic click through covering elements.
          await backdrop.click({ position: { x: 2, y: backdropBox.height - 4 } });
          check(await toggle.getAttribute('aria-expanded') === 'false', `${kind}-${width}: backdrop dismisses`);
          await toggle.evaluate(el => el.blur());
        } else {
          check(!await toggle.isVisible(), `${kind}-${width}: desktop toggle absent`);
          if (kind === 'admin') check(await panel.isVisible(), `${width}: desktop sidebar retained`);
        }
        await audit(page, `${kind}-${width}-closed`);
        results.push({ kind, width, ...metrics, status: 'PASS' });
      }
      await context.close();
    }

    // Roles and current-page highlighting use the real PHP conditional layout.
    for (const role of ['super_admin', 'admin']) {
      const { page, context } = await pageFor(390);
      for (const route of ['dashboard', 'inquiries', 'advisor', 'categories', 'products', 'media', 'settings', 'admins']) {
        await page.goto(`http://127.0.0.1:8837/axa-kenmark/admin/?r=${route}&role=${role}`);
        const panel = page.locator('[data-nav-panel]');
        check(await panel.locator('.admin-nav-link').count() === (role === 'admin' ? 7 : 9), `${role}-${route}: original role destinations`);
        const restricted = panel.locator('a[href*="r=settings"], a[href*="r=admins"]');
        check(await restricted.count() === (role === 'admin' ? 0 : 2), `${role}-${route}: restricted links unchanged`);
        const active = panel.locator('[aria-current="page"]');
        if (role === 'super_admin' || !['settings', 'admins'].includes(route)) {
          check((await active.getAttribute('href')).includes(`r=${route}`), `${role}-${route}: correct active page`);
        }
      }
      await context.close();
    }

    // Sustained scrolling, idle restore, keyboard focus and admin editing.
    for (const kind of ['public', 'admin']) {
      const { page, context } = await pageFor(390, 'no-preference');
      await page.goto(`http://127.0.0.1:8837/axa-kenmark/${kind === 'admin' ? 'admin/?r=dashboard' : ''}`, { waitUntil: 'networkidle' });
      await page.waitForFunction(() => !document.getElementById('axa-loader'));
      await page.evaluate(() => { document.documentElement.style.scrollBehavior = 'auto'; });
      const nav = page.locator('[data-navigation]');
      // Sample the timing within the browser, avoiding protocol/host scheduling
      // latency between the last scroll event and the early-idle assertion.
      const timing = await page.evaluate(async () => {
        const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
        const nav = document.querySelector('[data-navigation]');
        for (let y = 100; y <= 500; y += 50) { scrollTo(0, y); await wait(45); }
        const hidden = nav.classList.contains('nav-is-scrolling');
        const hiddenY = nav.querySelector('[data-nav-header]').getBoundingClientRect().y;
        await wait(100);
        const earlyHidden = nav.classList.contains('nav-is-scrolling');
        await wait(450);
        return { hidden, hiddenY, earlyHidden, restored: !nav.classList.contains('nav-is-scrolling'),
          restoredY: nav.querySelector('[data-nav-header]').getBoundingClientRect().y };
      });
      check(timing.hidden, `${kind}: hide during active scroll`);
      check(timing.hiddenY < -60, `${kind}: slides upward`);
      check(timing.earlyHidden, `${kind}: no premature idle restore`);
      check(timing.restored, `${kind}: restores after approximately 300ms`);
      check(Math.abs(timing.restoredY) < 1, `${kind}: restored at viewport top`);
      await nav.locator('[data-nav-toggle]').focus();
      await page.evaluate(() => scrollBy(0, 100)); await page.waitForTimeout(50);
      check(!await nav.evaluate(el => el.classList.contains('nav-is-scrolling')), `${kind}: keyboard focus pins header`);
      await nav.locator('[data-nav-toggle]').click();
      const initialScroll = await page.evaluate(() => scrollY);
      await page.mouse.move(5, 500); await page.mouse.wheel(0, 300); await page.waitForTimeout(80);
      check(await page.evaluate(() => scrollY) === initialScroll, `${kind}: backdrop wheel cannot scroll background`);
      check(!await nav.evaluate(el => el.classList.contains('nav-is-scrolling')), `${kind}: open menu pins header`);
      await page.keyboard.press('Escape');
      if (kind === 'admin') {
        await page.locator('#admin-content').evaluate(el => {
          const form = document.createElement('form');
          form.innerHTML = '<label for="fixture-edit">Synthetic editor</label><input id="fixture-edit" value=""><button type="button" id="fixture-critical">Preview unsaved changes</button>';
          el.prepend(form);
        });
        await page.locator('#fixture-edit').fill('Unsaved navigation test');
        await page.evaluate(() => scrollBy(0, 200)); await page.waitForTimeout(60);
        check(!await nav.evaluate(el => el.classList.contains('nav-is-scrolling')), 'admin: editing pins header');
        await page.locator('#fixture-critical').focus();
        await page.evaluate(() => scrollBy(0, 150)); await page.waitForTimeout(60);
        check(!await nav.evaluate(el => el.classList.contains('nav-is-scrolling')), 'admin: focused form action pins header');
      } else {
        await page.evaluate(() => document.getElementById('options-title').scrollIntoView());
        await page.waitForTimeout(100);
        check(await nav.locator('[data-nav-section="options-title"]').getAttribute('aria-current') === 'location', 'public: section highlight follows scrolling');
        await nav.locator('[data-nav-toggle]').click();
        await nav.locator('.nav-consultation').click();
        check(await page.locator('#contact-consultation').evaluate(el => el.open), 'consultation CTA opens existing details');
        check(await page.locator('#contact-consultation').evaluate(el => el === document.activeElement), 'consultation CTA moves focus to content');
        check(await nav.locator('[data-nav-toggle]').getAttribute('aria-expanded') === 'false', 'CTA closes navigation');
      }
      await context.close();
    }

    // Reduced motion, resized open drawers, short landscapes, privacy and no-JS fallback.
    for (const kind of ['public', 'admin']) {
      const { page, context } = await pageFor(390, 'reduce', 420);
      await page.goto(`http://127.0.0.1:8837/axa-kenmark/${kind === 'admin' ? 'admin/?r=dashboard' : ''}`, { waitUntil: 'networkidle' });
      await page.waitForFunction(() => !document.getElementById('axa-loader'));
      await page.evaluate(() => scrollTo(0, 400)); await page.waitForTimeout(70);
      check(!await page.locator('[data-navigation]').evaluate(el => el.classList.contains('nav-is-scrolling')), `${kind}: reduced motion keeps header visible`);
      await page.locator('[data-nav-toggle]').click();
      await page.locator('[data-nav-panel]').evaluate(el => { el.scrollTop = el.scrollHeight; });
      const lastControl = page.locator(kind === 'admin' ? '.admin-logout' : '.nav-consultation');
      check(await lastControl.evaluate(el => { const r = el.getBoundingClientRect(); return r.top >= 72 && r.bottom <= innerHeight; }), `${kind}: short screen essential action reachable`);
      await page.setViewportSize({ width: 1024, height: 960 });
      check(await page.locator('body').evaluate(el => !el.classList.contains('nav-scroll-locked')), `${kind}: resize unlocks background`);
      check(await page.locator('[data-nav-panel]').evaluate(el => !el.inert), `${kind}: desktop panel not inert`);
      check(await page.evaluate(() => {
        const el = document.activeElement;
        return el !== document.body && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
      }), `${kind}: resize keeps focus on a visible navigation control`);
      await page.setViewportSize({ width: 390, height: 960 });
      check(await page.locator('[data-nav-panel]').evaluate(el => el.inert), `${kind}: resizing back closes mobile menu`);
      await context.close();
    }
    {
      const { page, context } = await pageFor(390);
      await page.goto('http://127.0.0.1:8837/axa-kenmark/privacy', { waitUntil: 'networkidle' });
      await page.waitForFunction(() => !document.getElementById('axa-loader'));
      await page.locator('[data-nav-toggle]').click();
      check(await page.locator('.public-nav-link[aria-current]').count() === 0, 'privacy has no false landing-section highlight');
      check((await page.locator('.public-nav-link').first().getAttribute('href')).endsWith('/#home'), 'privacy navigation retains landing destination');
      await audit(page, 'privacy-mobile-open');
      await context.close();
    }
    for (const kind of ['public', 'admin']) {
      const { page, context } = await pageFor(390);
      let releaseNavigation;
      const held = new Promise(resolve => { releaseNavigation = resolve; });
      await page.route('**/navigation.js*', async route => { await held; await route.continue(); });
      const load = page.goto(`http://127.0.0.1:8837/axa-kenmark/${kind === 'admin' ? 'admin/?r=dashboard' : ''}`);
      try {
        await page.waitForFunction(() => [...document.styleSheets].some(sheet => sheet.href?.includes('navigation.css')));
        const selector = kind === 'admin' ? '.admin-topbar' : '.hero';
        const beforeLoad = await page.locator(selector).boundingBox();
        check(await page.locator('[data-nav-panel]').evaluate(el => getComputedStyle(el).display === 'none'), `${kind}: no pre-init menu flash`);
        releaseNavigation();
        await load;
        const afterLoad = await page.locator(selector).boundingBox();
        check(beforeLoad.y === afterLoad.y, `${kind}: no navigation initialization layout shift`);
      } finally { releaseNavigation(); await context.close(); }
    }
    for (const kind of ['public', 'admin']) {
      const { page, context } = await pageFor(320, 'reduce', 960, false);
      await page.goto(`http://127.0.0.1:8837/axa-kenmark/${kind === 'admin' ? 'admin/?r=dashboard' : ''}`, { waitUntil: 'networkidle' });
      check(await page.locator(kind === 'admin' ? '.admin-nav-link' : '.public-nav-link').first().isVisible(), `${kind}: no-JS destinations remain accessible`);
      await context.close();
    }
    check(consoleErrors.length === 0, `console errors: ${JSON.stringify(consoleErrors)}`);
    check(pageErrors.length === 0, `uncaught errors: ${JSON.stringify(pageErrors)}`);
    check(requestErrors.length === 0, `failed/missing resources: ${JSON.stringify(requestErrors)}`);
    fs.writeFileSync(path.join(root, 'interaction-results.json'), JSON.stringify({ status: 'PASS', checks, results, consoleErrors, pageErrors, requestErrors }, null, 2));
    console.log(`PASS: ${checks} browser checks; nine widths, 33 axe audits, original destinations, role fixtures, keyboard, scroll, resize, reduced motion and no-JS fallback. No DB/auth/session/server mutations.`);
  } finally { await browser.close(); server.close(); }
}
main().catch(error => { console.error(error); server.close(); process.exitCode = 1; });
