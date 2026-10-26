const fs = require('fs');
const path = require('path');
const { chromium } = require('@playwright/test');
const S = require('../support');

const ROOT = path.resolve(__dirname, '../../..');
const OUT = path.join(ROOT, 'docs');
const BASE = process.env.AIT_BASE_URL || 'http://localhost:8080/AIT/';

async function renderDiagrams(browser) {
  const page = await browser.newPage();
  for (const name of ['erd', 'architecture']) {
    const source = fs.readFileSync(path.join(OUT, 'diagrams', `${name}.mmd`), 'utf8');
    await page.setContent(`<!doctype html><html><body style="margin:0;background:#fff">
      <pre class="mermaid">${source.replace(/</g, '&lt;')}</pre>
      <script src="https://cdn.jsdelivr.net/npm/mermaid@10.9.1/dist/mermaid.min.js"></script>
      <script>mermaid.initialize({ startOnLoad: true, theme: 'neutral', er: { useMaxWidth: false }, flowchart: { useMaxWidth: false } });</script>
      </body></html>`);
    await page.waitForSelector('pre.mermaid svg', { timeout: 30000 });
    const svg = await page.$eval('pre.mermaid svg', (el) => el.outerHTML);
    fs.writeFileSync(path.join(OUT, 'diagrams', `${name}.svg`), svg);
    console.log(`diagram: docs/diagrams/${name}.svg`);
  }
  await page.close();
}

async function shot(page, url, file, options = {}) {
  if (url !== null) await page.goto(new URL(url, BASE).toString());
  await page.waitForLoadState('load');
  await page.waitForTimeout(options.wait ?? 1500);
  if (options.before) await options.before(page);
  await page.screenshot({ path: path.join(OUT, 'screenshots', file), type: 'jpeg', quality: 78, fullPage: options.fullPage ?? false });
  console.log(`screenshot: docs/screenshots/${file}`);
}

async function main() {
  fs.mkdirSync(path.join(OUT, 'screenshots'), { recursive: true });
  const browser = await chromium.launch();
  await renderDiagrams(browser);

  const desktop = { viewport: { width: 1440, height: 900 }, baseURL: BASE };
  const visitor = await browser.newPage(desktop);
  await shot(visitor, '', 'home.jpg');
  await shot(visitor, 'programs', 'programs.jpg');
  await shot(visitor, 'admissions', 'admissions.jpg', { before: (p) => p.locator('text=Spring 2027 admission schedule').scrollIntoViewIfNeeded() });
  await shot(visitor, 'faq', 'faq.jpg');
  await shot(visitor, 'registration', 'registration.jpg');

  const dark = await browser.newPage({ ...desktop, colorScheme: 'dark' });
  await shot(dark, '', 'home-dark.jpg');

  const phone = await browser.newPage({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, baseURL: BASE });
  await shot(phone, 'campus-life', 'mobile-campus-life.jpg');

  const J = S.state.load();
  const E = S.env();
  const admin = await browser.newPage(desktop);
  await S.adminLogin(admin, E.E2E_ADMIN_EMAIL, E.E2E_ADMIN_PASSWORD, E.SUPERADMIN_SECRET_KEY);
  await shot(admin, null, 'admin-dashboard.jpg');
  await S.adminSection(admin, 'section-applications');
  await shot(admin, null, 'admin-applications.jpg');
  const review = admin.locator('#section-applications button', { hasText: 'Review' }).first();
  await review.click();
  await admin.locator('.modal.show').waitFor();
  await shot(admin, null, 'admin-review-documents.jpg', { before: (p) => p.locator('.modal.show h6', { hasText: 'Submitted documents' }).scrollIntoViewIfNeeded() });
  await shot(admin, 'admin/timetable.php?department=BSSE', 'admin-timetable.jpg');

  if (J.kamranEmail) {
    const teacher = await browser.newPage(desktop);
    await teacher.goto(new URL('teachers/login.php', BASE).toString());
    await teacher.fill('input[name="email"]', J.kamranEmail);
    await teacher.fill('input[name="password"]', J.kamranPassword);
    await teacher.locator('button[type="submit"]').click();
    await shot(teacher, null, 'teacher-dashboard.jpg');
    if (J.kamranSubjects) await shot(teacher, `teachers/attendance.php?subject_id=${J.kamranSubjects[0]}`, 'teacher-attendance.jpg');
  }

  if (J.saraCode) {
    const student = await browser.newPage(desktop);
    await student.goto(new URL('student-login', BASE).toString());
    await student.fill('input[name="identifier"]', J.saraCode);
    await student.fill('input[name="password"]', J.saraPassword);
    await student.locator('button[type="submit"]').click();
    await shot(student, null, 'student-dashboard.jpg');
  }

  await browser.close();
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
