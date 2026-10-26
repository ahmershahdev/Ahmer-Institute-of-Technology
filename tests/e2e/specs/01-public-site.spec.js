// Personas: Ayesha (prospective student reading the site), Bilal (parent using the contact form).
const { test } = require('@playwright/test');
const { expect, solveCaptcha, humanPause } = require('../support');

const PAGES = ['', 'about', 'programs', 'admissions', 'departments', 'faculty', 'campus-life', 'students', 'alumini', 'career', 'faq', 'contact', 'privacy-policy', 'terms-of-service'];

test.describe('Ayesha browses the public website', () => {
  for (const slug of PAGES) {
    test(`/${slug || ''} has complete, valid SEO and no errors`, async ({ page }) => {
      const problems = [];
      page.on('console', (m) => m.type() === 'error' && problems.push(m.text()));
      page.on('pageerror', (e) => problems.push(e.message));
      const response = await page.goto(slug);
      expect(response.status()).toBe(200);

      const title = await page.title();
      expect(title.length, `title "${title}"`).toBeGreaterThan(15);
      expect(title.length, `title "${title}"`).toBeLessThanOrEqual(60);

      const description = await page.locator('meta[name="description"]').getAttribute('content');
      expect(description.length, description).toBeGreaterThan(70);
      expect(description.length, description).toBeLessThanOrEqual(150);

      await expect(page.locator('h1')).toHaveCount(1);
      const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
      expect(canonical).toMatch(slug === '' ? /\/$/ : new RegExp(`/${slug}$`));
      for (const prop of ['og:title', 'og:description', 'og:url', 'og:image', 'og:type']) {
        await expect(page.locator(`meta[property="${prop}"]`)).toHaveCount(1);
      }
      await expect(page.locator('meta[name="twitter:card"]')).toHaveAttribute('content', 'summary_large_image');
      await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /index, follow/);

      const ld = JSON.parse(await page.locator('script[type="application/ld+json"]').innerText());
      const types = ld['@graph'].flatMap((n) => [].concat(n['@type']));
      expect(types).toEqual(expect.arrayContaining(['CollegeOrUniversity', 'WebSite', 'BreadcrumbList']));
      if (slug === 'faq') expect(ld['@graph'].find((n) => n['@type'] === 'FAQPage').mainEntity.length).toBeGreaterThan(5);
      if (slug === 'programs') expect(types).toContain('ItemList');
      if (slug !== '') await expect(page.locator('nav.breadcrumbs')).toBeVisible();

      // Heading levels never skip (h2 -> h4 etc.).
      const levels = await page.$$eval('h1,h2,h3,h4,h5,h6', (els) => els.map((e) => Number(e.tagName[1])));
      levels.forEach((level, i) => i > 0 && expect(level - levels[i - 1], `heading jump at ${i}`).toBeLessThanOrEqual(1));

      // Every image declares alt text (empty alt is fine for decorative images).
      expect(await page.locator('img:not([alt])').count()).toBe(0);
      expect(problems, problems.join('\n')).toEqual([]);
    });
  }

  test('security, compression and caching headers are in place', async ({ request }) => {
    const res = await request.get('admissions', { headers: { 'Accept-Encoding': 'br, gzip' } });
    const h = res.headers();
    expect(h['content-security-policy']).toMatch(/script-src 'self' 'nonce-[A-Za-z0-9_-]{20,}'/);
    expect(h['content-security-policy']).toContain("script-src-attr 'none'");
    expect(h['content-security-policy']).toContain("frame-ancestors 'none'");
    expect(h['content-security-policy']).not.toMatch(/script-src[^;]*unsafe-inline/);
    expect(h['x-frame-options']).toBe('DENY');
    expect(h['x-content-type-options']).toBe('nosniff');
    expect(h['referrer-policy']).toBe('strict-origin-when-cross-origin');
    expect(h['permissions-policy']).toContain('camera=()');
    expect(h['x-powered-by']).toBeUndefined();
    expect(h['content-encoding']).toBe('br');

    const second = await request.get('admissions');
    expect(second.headers()['x-cache']).toBe('HIT');
    const nonceA = (await request.get('about')).headers()['content-security-policy'];
    const nonceB = (await request.get('about')).headers()['content-security-policy'];
    expect(nonceA).not.toBe(nonceB);

    const html = await second.text();
    const css = html.match(/href="([^"]+public\.css\?v=[a-f0-9]+)"/)[1];
    const cssRes = await request.get(css.replace(/^\/AIT\//, ''));
    expect(cssRes.headers()['cache-control']).toContain('immutable');
  });

  test('sitemap.xml, robots.txt, llms.txt and the manifest are served', async ({ request }) => {
    const sitemap = await (await request.get('sitemap.xml')).text();
    for (const slug of PAGES.filter(Boolean)) expect(sitemap).toContain(`/${slug}</loc>`);
    expect(sitemap).toContain('<lastmod>');
    const robots = await (await request.get('robots.txt')).text();
    expect(robots).toMatch(/Disallow: \/AIT\/admin\//);
    expect(robots).toMatch(/Sitemap: https?:\/\/.+\/sitemap\.xml/);
    expect((await request.get('llms.txt')).status()).toBe(200);
    expect((await request.get('llms-full.txt')).status()).toBe(200);
    expect((await (await request.get('site.webmanifest')).json()).short_name).toBe('AIT');
  });

  test('/home and .php URLs redirect to one canonical address', async ({ request }) => {
    for (const [from, to] of [['home', /\/AIT\/$/], ['about.php', /\/AIT\/about$/], ['pages/programs.php', /\/AIT\/programs$/]]) {
      const res = await request.get(from, { maxRedirects: 0 });
      expect(res.status()).toBe(301);
      expect(res.headers().location).toMatch(to);
    }
    expect((await request.get('no-such-page')).status()).toBe(404);
  });

  test('mobile navigation opens, closes, and the theme toggle persists', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('');
    const toggle = page.locator('.nav-toggle');
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await page.keyboard.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await page.locator('[data-theme-toggle]').click();
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  });
});

test.describe('Bilal writes to the admissions office', () => {
  test('sends a message after answering the quick check', async ({ page }) => {
    await page.goto('contact');
    const form = page.locator('form[action$="contact"]');
    await form.locator('input[name="name"]').fill('Bilal Ahmed');
    await form.locator('input[name="email"]').fill('bilal.parent@example.com');
    await form.locator('input[name="subject"]').fill('Hostel places for first-year students');
    await form.locator('textarea[name="message"]').fill('My son is applying for BS Civil Engineering. Are hostel rooms guaranteed for students from Larkana?');
    await solveCaptcha(form);
    await humanPause(page);
    await form.locator('button[type="submit"]').click();
    await expect(page.locator('.form-alert.is-success')).toContainText('Thank you, Bilal Ahmed');
  });

  test('a bot that posts the instant the page loads is refused', async ({ page }) => {
    // A script fetches the form and posts back in the same breath.
    const html = await (await page.request.get('contact')).text();
    const pick = (re) => html.match(re)[1];
    const [, a, b] = html.match(/What is <strong>(\d+) \+ (\d+)<\/strong>/);
    const res = await page.request.post('contact', {
      form: {
        csrf_token: pick(/name="csrf_token" value="([a-f0-9]+)"/),
        form_ts: pick(/name="form_ts" value="([^"]+)"/),
        captcha_answer: String(Number(a) + Number(b)),
        website: '',
        name: 'Fast Bot',
        email: 'bot@example.com',
        subject: 'Hello',
        message: 'Buy cheap followers today, limited offer!',
      },
      headers: { 'Sec-Fetch-Site': 'same-origin' },
    });
    expect(await res.text()).toContain('too quick');
  });

  test('a filled honeypot field is rejected even with a correct answer', async ({ page }) => {
    await page.goto('contact');
    const form = page.locator('form[action$="contact"]');
    await form.locator('input[name="name"]').fill('Hidden Field Bot');
    await form.locator('input[name="email"]').fill('bot2@example.com');
    await form.locator('input[name="subject"]').fill('Offer');
    await form.locator('textarea[name="message"]').fill('Automated message from a crawler that fills every input.');
    await form.locator('input[name="website"]').fill('http://spam.example', { force: true });
    await solveCaptcha(form);
    await humanPause(page);
    await form.locator('button[type="submit"]').click();
    await expect(page.locator('.form-alert.is-error')).toContainText('could not be verified');
  });

  test('a wrong answer to the quick check is refused and the typed values are kept', async ({ page }) => {
    await page.goto('contact');
    const form = page.locator('form[action$="contact"]');
    await form.locator('input[name="name"]').fill('<script>alert(1)</script>');
    await form.locator('input[name="email"]').fill('bilal.parent@example.com');
    await form.locator('input[name="subject"]').fill('Fees');
    await form.locator('textarea[name="message"]').fill('What is the fee for BBA?');
    await form.locator('label.ait-captcha input').fill('99');
    await humanPause(page);
    await form.locator('button[type="submit"]').click();
    await expect(page.locator('.form-alert.is-error')).toContainText('quick check');
    // The script tag comes back as inert text inside the field, never as markup.
    await expect(page.locator('input[name="name"]')).toHaveValue('<script>alert(1)</script>');
    expect(await page.locator('script:has-text("alert(1)")').count()).toBe(0);
  });
});
