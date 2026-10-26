const { test } = require('@playwright/test');
const S = require('../support');
const { expect } = S;

const J = S.state.load();
const sameOrigin = { 'Sec-Fetch-Site': 'same-origin' };

async function studentSession(page) {
  await page.goto('student-login');
  await page.fill('input[name="identifier"]', J.saraCode);
  await page.fill('input[name="password"]', J.saraPassword);
  await page.locator('button[type="submit"]').click();
  await expect(page.locator('body')).toContainText(J.saraCode);
}

async function teacherSession(page) {
  await page.goto('teachers/login.php');
  await page.fill('input[name="email"]', J.kamranEmail);
  await page.fill('input[name="password"]', J.kamranPassword);
  await page.locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/teachers\/dashboard\.php/);
}

test.describe('Eve: access control', () => {
  test.beforeAll(() => {
    if (!J.saraCode) throw new Error('Run specs/02-admissions-journey.spec.js first.');
  });

  test('every private page redirects anonymous visitors to a login', async ({ request }) => {
    const pages = {
      dashboard: /login/, 'student-dashboard': /student-login/, 'admin/dashboard.php': /login\.php/, 'admin/teachers.php': /login\.php/,
      'admin/staff.php': /login\.php/, 'admin/catalog.php': /login\.php/, 'admin/timetable.php': /login\.php/,
      'teachers/dashboard.php': /login\.php/, 'teachers/attendance.php': /login\.php/, 'teachers/marks.php': /login\.php/,
      'staff/dashboard.php': /login\.php/, 'staff/department.php': /login\.php/,
    };
    for (const [path, target] of Object.entries(pages)) {
      const res = await request.get(path, { maxRedirects: 0 });
      expect(res.status(), path).toBe(302);
      expect(res.headers().location, path).toMatch(target);
    }
    for (const path of ['generate_challan.php', 'generate_slip.php', 'generate_semester_challan.php']) {
      expect([302, 403], path).toContain((await request.get(path, { maxRedirects: 0 })).status());
    }
    expect((await request.get('admin/catalog_teachers_for_subject.php?subject_id=1')).status()).toBe(401);
    expect((await request.get('download_file.php?path=uploads/documents/x.webp')).status()).toBe(403);
  });

  test('configuration, source, data and tooling are never served', async ({ request }) => {
    for (const path of ['.env', '.env.docker', '.env.example', '.git/config', '.htaccess', 'docker-compose.yml', 'docker/Dockerfile', 'database/schema.sql',
      'backend/pdo.php', 'backend/security.php', 'bin/create-admin.php', 'tests/php/run.php', 'README.md', 'SECURITY.md', 'uploads/.htaccess', 'backend/storage/']) {
      expect([403, 404], path).toContain((await request.get(path, { maxRedirects: 0 })).status());
    }
  });

  test('an applicant cannot download another applicant\'s documents (IDOR)', async ({ page }) => {
    await page.goto('login');
    await page.fill('input[name="email"]', J.usman.email);
    await page.fill('input[name="password"]', J.usman.password);
    await page.locator('#btnSubmit').click();
    await expect(page).toHaveURL(/\/dashboard/);
    const res = await page.request.get(`download_file.php?path=${encodeURIComponent(J.saraDocumentPath)}`);
    expect(res.status()).toBe(404);
    for (const probe of ['uploads/../.env', '../.env', '/etc/passwd', 'uploads/documents/../../backend/pdo.php', 'uploads%2f..%2f.env']) {
      expect((await page.request.get(`download_file.php?path=${encodeURIComponent(probe)}`)).status(), probe).toBe(404);
    }
  });

  test('the owner gets her file as a sandboxed attachment', async ({ page }) => {
    await studentSession(page);
    const res = await page.request.get(`download_file.php?path=${encodeURIComponent(J.saraDocumentPath)}`);
    expect(res.status()).toBe(200);
    expect(res.headers()['content-disposition']).toMatch(/^attachment;/);
    expect(res.headers()['content-security-policy']).toContain('sandbox');
    expect(res.headers()['cache-control']).toContain('no-store');
  });

  test('a student session cannot reach admin, teacher, or staff pages', async ({ page }) => {
    await studentSession(page);
    for (const path of ['admin/dashboard.php', 'teachers/dashboard.php', 'staff/dashboard.php']) {
      const res = await page.request.get(path, { maxRedirects: 0 });
      expect(res.status(), path).toBe(302);
    }
  });

  test('a teacher cannot read or write another teacher\'s class', async ({ page }) => {
    await teacherSession(page);
    const res = await page.goto('teachers/attendance.php?subject_id=1');
    expect(res.status()).toBe(200);
    await expect(page.locator('input[type="radio"]')).toHaveCount(0);
    const token = await page.locator('input[name="csrf_token"]').first().inputValue();
    const post = await page.request.post('teachers/attendance.php', { form: { csrf_token: token, subject_id: '1', class_date: new Date().toISOString().slice(0, 10), 'status[1]': 'absent' }, headers: sameOrigin });
    expect(await post.text()).toContain('You are not assigned to that subject.');
  });
});

test.describe('Eve: request forgery and injection', () => {
  test('posts without a CSRF token, or from another site, are refused', async ({ page }) => {
    await page.goto('contact');
    const noToken = await page.request.post('contact', { form: { name: 'x', email: 'x@example.com', subject: 's', message: 'hello there friend' }, headers: sameOrigin });
    expect(noToken.status()).toBe(403);
    const token = await page.locator('input[name="csrf_token"]').inputValue();
    const crossSite = await page.request.post('contact', { form: { csrf_token: token, name: 'x' }, headers: { 'Sec-Fetch-Site': 'cross-site' } });
    expect(crossSite.status()).toBe(403);
    const wrongToken = await page.request.post('teachers/login.php', { form: { csrf_token: 'a'.repeat(64), email: 'a@b.co', password: 'x' }, headers: sameOrigin });
    expect(wrongToken.status()).toBe(403);
  });

  test('logout only works as a CSRF-protected POST', async ({ page }) => {
    await studentSession(page);
    await page.goto('logout');
    await page.goto('student-dashboard');
    await expect(page.locator('body')).toContainText(J.saraCode);
    await page.locator('nav.student-nav button[type="submit"]').click();
    await page.waitForURL(/student-login/);
    await page.goto('student-dashboard');
    await expect(page).toHaveURL(/student-login/);
  });

  test('SQL injection strings are treated as plain data', async ({ page }) => {
    for (const [path, field, submit] of [['student-login', 'identifier', 'button[type="submit"]'], ['teachers/login.php', 'email', 'button[type="submit"]']]) {
      await page.goto(path);
      await page.fill(`input[name="${field}"]`, field === 'email' ? "x'or'1'='1@example.com" : "' OR '1'='1' -- ");
      await page.fill('input[name="password"]', "' OR '1'='1");
      const [res] = await Promise.all([page.waitForResponse((r) => r.request().method() === 'POST'), page.locator(submit).click()]);
      expect(res.status(), path).toBeLessThan(500);
      await expect(page.locator('body')).toContainText(/incorrect|invalid|valid email/i);
    }
  });

  test('stored XSS in an announcement renders as text for students', async ({ browser }) => {
    const payload = `<img src=x onerror="document.body.dataset.pwned=1"><script>document.body.dataset.pwned=1</script>`;
    const teacherCtx = await browser.newContext();
    const teacher = await teacherCtx.newPage();
    await teacherSession(teacher);
    await teacher.goto('teachers/announcements.php');
    await teacher.fill('input[name="title"]', `Lab notice ${payload}`);
    await teacher.fill('textarea[name="body"]', `Bring your ID card. ${payload}`);
    await teacher.locator('form:has(textarea[name="body"]) button[type="submit"]').click();
    await expect(teacher.locator('body')).toContainText('Announcement posted.');
    expect(await teacher.evaluate(() => document.body.dataset.pwned)).toBeUndefined();

    const studentCtx = await browser.newContext();
    const student = await studentCtx.newPage();
    let dialog = false;
    student.on('dialog', (d) => {
      dialog = true;
      d.dismiss();
    });
    await studentSession(student);
    await expect(student.locator('body')).toContainText('Lab notice <img src=x');
    expect(await student.evaluate(() => document.body.dataset.pwned)).toBeUndefined();
    expect(dialog).toBe(false);
    await teacherCtx.close();
    await studentCtx.close();
  });
});

test.describe('Eve: brute force and automation', () => {
  test('after three failures a login asks for the quick check; after five the account locks', async ({ page }) => {
    const hamza = J.hamza;
    await page.goto('login');
    const attempt = async (password) => {
      await page.fill('input[name="email"]', hamza.email);
      await page.fill('input[name="password"]', password);
      await S.solveCaptcha(page);
      await Promise.all([page.waitForResponse((r) => r.request().method() === 'POST'), page.locator('#btnSubmit').click()]);
      await page.waitForTimeout(2300);
    };
    for (let i = 1; i <= 5; i++) {
      await attempt(`Wrong-Guess-${i}x`);
      if (i === 3) await expect(page.locator('label.ait-captcha')).toBeVisible();
    }
    await attempt(hamza.password);
    await expect(page.locator('#errorAlertContainer')).toContainText('temporarily locked');
  });

  test('a registration corrected without reloading the page still passes the bot check', async ({ page }) => {
    await page.goto('registration');
    const fill = async (password) => {
      await page.fill('#name', 'Mehwish Laghari');
      await page.fill('#fatherName', 'Rasool Bux Laghari');
      await page.fill('#email', `mehwish.${S.run}@example.com`);
      await page.fill('#cnic', S.cnic());
      await page.fill('#dob', '2004-08-09');
      await page.fill('#phone', '+923331234567');
      await page.fill('input[name="password"]', password);
      await page.fill('input[name="retype_password"]', password);
      await S.solveCaptcha(page);
      await S.humanPause(page);
      await page.locator('#submitBtn').click();
    };
    await fill('Password12345');
    await expect(page.locator('#alertContainer')).toContainText('too easy to guess');
    await page.waitForTimeout(2200);
    await fill('Shah-Abdul-Latif-77');
    await expect(page.locator('#alertContainer')).toContainText('Registration successful');
  });

  test('repeated password-recovery attempts are rate limited with Retry-After', async ({ page }) => {
    await page.goto('admin/forgot-password.php');
    const token = await page.locator('input[name="csrf_token"]').inputValue();
    let last;
    for (let i = 0; i < 4; i++) {
      last = await page.request.post('admin/forgot-password.php', {
        form: { csrf_token: token, email: 'nobody@ait.test', secret_key: 'guess', password: 'Lantern-Orbit-58', password_confirmation: 'Lantern-Orbit-58' },
        headers: sameOrigin,
      });
    }
    expect(last.status()).toBe(429);
    expect(Number(last.headers()['retry-after'])).toBeGreaterThan(0);
  });

  test('session cookies are HttpOnly and SameSite=Strict', async ({ page, context }) => {
    await page.goto('login');
    const cookie = (await context.cookies()).find((c) => c.name === 'PHPSESSID');
    expect(cookie.httpOnly).toBe(true);
    expect(cookie.sameSite).toBe('Strict');
  });
});

test.describe('Eve: no policy violations anywhere', () => {
  test('portal pages load without CSP violations or script errors', async ({ browser }) => {
    test.setTimeout(240_000);
    const visit = async (page, path) => {
      const errors = [];
      page.on('console', (m) => m.type() === 'error' && errors.push(`${path}: ${m.text()}`));
      page.on('pageerror', (e) => errors.push(`${path}: ${e.message}`));
      await page.goto(path);
      await page.waitForLoadState('load');
      await page.waitForTimeout(400);
      return errors;
    };
    const anon = await browser.newPage();
    let problems = [];
    for (const path of ['login', 'registration', 'student-login', 'admin/login.php', 'admin/forgot-password.php', 'teachers/login.php', 'staff/login.php', `teachers/${J.kamranCode}`, 'no-such-page']) {
      problems = problems.concat(await visit(anon, path));
    }
    const student = await browser.newPage();
    await studentSession(student);
    problems = problems.concat(await visit(student, 'student-dashboard'));
    const teacher = await browser.newPage();
    await teacherSession(teacher);
    for (const path of ['teachers/dashboard.php', 'teachers/attendance.php', 'teachers/marks.php', 'teachers/students.php', 'teachers/subjects.php', 'teachers/timetable.php', 'teachers/announcements.php']) {
      problems = problems.concat(await visit(teacher, path));
    }
    const admin = await browser.newPage();
    const E = S.env();
    await S.adminLogin(admin, E.E2E_ADMIN_EMAIL, E.E2E_ADMIN_PASSWORD, E.SUPERADMIN_SECRET_KEY);
    for (const path of ['admin/dashboard.php', 'admin/teachers.php', 'admin/staff.php', 'admin/catalog.php', 'admin/timetable.php']) {
      problems = problems.concat(await visit(admin, path));
    }
    problems = problems.filter((p) => !/Failed to load resource: the server responded with a status of 404/.test(p) || /no-such-page/.test(p) === false && false);
    expect(problems, problems.join('\n')).toEqual([]);
  });
});
