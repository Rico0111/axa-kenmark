/* Local static fixture capture; never serves the application or accepts writes. */
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(process.argv[2]);
const widths = [320, 375, 390, 408, 430, 640, 768, 1024, 1440];
const types = { '.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.jpg': 'image/jpeg', '.ico': 'image/x-icon', '.html': 'text/html; charset=utf-8' };
const server = http.createServer((req, res) => {
  if (req.method !== 'GET') { res.writeHead(405); return res.end(); }
  const url = new URL(req.url, 'http://127.0.0.1:8837');
  let file = 'public.html';
  if (url.pathname.includes('/assets/')) file = url.pathname.split('/axa-kenmark/')[1];
  else if (url.pathname.includes('/admin')) file = `${url.searchParams.get('role') === 'admin' ? 'admin' : 'super_admin'}-${url.searchParams.get('r') || 'dashboard'}.html`;
  else if (url.pathname.endsWith('/privacy')) file = 'privacy.html';
  const full = path.resolve(root, file);
  if (!full.startsWith(root + path.sep) || !fs.existsSync(full)) { res.writeHead(404); return res.end(); }
  const body = fs.readFileSync(full);
  const headers = { 'Content-Type': types[path.extname(full)] || 'application/octet-stream', 'X-Content-Type-Options': 'nosniff' };
  if (path.extname(full) === '.html') {
    const nonce = body.toString().match(/nonce="([^"]+)"/)?.[1] || 'fixture';
    headers['Content-Security-Policy'] = `default-src 'self'; img-src 'self' data: https://images.unsplash.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'nonce-${nonce}'; connect-src 'self' https://fonts.googleapis.com; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'`;
  }
  res.writeHead(200, headers);
  res.end(body);
});
async function main() {
  await new Promise(resolve => server.listen(8837, '127.0.0.1', resolve));
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  const results = [];
  fs.mkdirSync(path.join(root, 'screenshots'), { recursive: true });
  try {
    for (const width of widths) {
      const context = await browser.newContext({ viewport: { width, height: 960 }, reducedMotion: 'reduce' });
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
      for (const kind of ['public', 'admin']) {
        await page.goto(`http://127.0.0.1:8837/axa-kenmark/${kind === 'admin' ? 'admin/?r=dashboard' : ''}`, { waitUntil: 'networkidle' });
        await page.waitForFunction(() => !document.getElementById('axa-loader'));
        await page.screenshot({ path: path.join(root, 'screenshots', `${kind}-${width}-closed.png`) });
        const metrics = await page.evaluate(() => ({
          overflow: document.documentElement.scrollWidth - innerWidth,
          navigationHeight: Math.round((innerWidth <= 900 && document.querySelector('.admin-mobile-header') || document.querySelector('.admin-sidebar, .topbar')).getBoundingClientRect().height),
          adminLinks: [...document.querySelectorAll('.admin-nav a')].map(a => ({ text: a.textContent.trim(), href: a.getAttribute('href') })),
        }));
        results.push({ width, kind, ...metrics, errors: [...errors] });
      }
      await context.close();
    }
    fs.writeFileSync(path.join(root, 'capture-results.json'), JSON.stringify(results, null, 2));
    console.log(JSON.stringify(results.map(({ width, kind, overflow, navigationHeight, errors }) => ({ width, kind, overflow, navigationHeight, errors })), null, 2));
  } finally { await browser.close(); server.close(); }
}
module.exports = { server, widths };
if (require.main === module) main().catch(error => { console.error(error); server.close(); process.exitCode = 1; });
