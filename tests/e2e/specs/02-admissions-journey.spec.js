// One admissions cycle, end to end, in the order it happens in real life.
//
// Personas
//   Sara    applicant who is admitted and becomes an enrolled student
//   Usman   applicant who is rejected with a review note
//   Zainab  applicant whose first upload attempts are malicious or malformed
//   Hamza   applicant who makes every common registration mistake
//   Imran   super administrator (bootstrap account from bin/create-admin.php)
//   Nadia   sub-admin allowed to manage teachers only
//   Omar    sub-admin allowed to decide admissions only
//   Kamran  teacher created by Nadia
//   Farah   Head of Department (staff portal)
//   Ali     security guard (staff portal, no department access)
const { test } = require('@playwright/test');
const S = require('../support');
const { expect } = S;

test.describe.configure({ mode: 'serial' });

const E = S.env();
const run = shared_run();
function shared_run() {
  return process.env.AIT_FRESH === '0' ? S.state.load().run || S.run : S.run;
}
const people = {
  sara: { name: 'Sara Memon', father: "Ghulam Qadir Memon", email: `sara.${run}@example.com`, cnic: S.cnic(), password: 'Indus-River-2027', newPassword: 'Kirthar-Range-91' },
  usman: { name: 'Usman Shaikh', father: 'Abdul Ghani Shaikh', email: `usman.${run}@example.com`, cnic: S.cnic(), password: 'Makli-Hills-4455' },
  zainab: { name: "Zainab D'Souza", father: 'Peter DSouza', email: `zainab.${run}@example.com`, cnic: S.cnic(), password: 'Clifton-Beach-718' },
  hamza: { name: 'Hamza Baloch', father: 'Karim Baloch', email: `hamza.${run}@example.com`, cnic: S.cnic(), password: 'Gwadar-Port-6612' },
  nadia: { name: 'Nadia Qureshi', email: `nadia.${run}@ait.test`, password: 'Ranikot-Fort-2207' },
  omar: { name: 'Omar Farooq', email: `omar.${run}@ait.test`, password: 'Sukkur-Barrage-3390' },
  kamran: { name: 'Kamran Abbasi', email: `kamran.${run}@ait.test`, newPassword: 'Thar-Desert-5530' },
  farah: { name: 'Farah Siddiqui', email: `farah.${run}@ait.test`, newPassword: 'Keenjhar-Lake-8812' },
  ali: { name: 'Ali Raza', email: `ali.${run}@ait.test`, newPassword: 'Haleji-Lake-4471' },
};
const admin = { email: E.E2E_ADMIN_EMAIL, password: E.E2E_ADMIN_PASSWORD, secret: E.SUPERADMIN_SECRET_KEY };
// Persisted after every test so any step can be re-run on its own while debugging.
const shared = S.state.load();
// Credentials the security spec (03) reuses; saved before any test runs.
Object.assign(shared, { saraEmail: people.sara.email, saraPassword: people.sara.newPassword, usman: people.usman, hamza: people.hamza, kamranEmail: people.kamran.email, kamranPassword: people.kamran.newPassword });
S.state.save(shared);
test.afterEach(() => S.state.save(shared));

async function register(page, p) {
  await page.goto('registration');
  await page.fill('#name', p.name);
  await page.fill('#fatherName', p.father);
  await page.fill('#email', p.email);
  await page.fill('#cnic', p.cnic);
  await page.fill('#dob', '2005-03-14');
  await page.fill('#phone', '+923001234567');
  await page.fill('input[name="password"]', p.password);
  await page.fill('input[name="retype_password"]', p.password);
  await S.solveCaptcha(page);
  await S.humanPause(page);
  await page.locator('#submitBtn').click();
  // Client-side validation may stop the form before it is sent; only a sent form
  // produces a server alert. On success the page moves itself to the login screen
  // after a short pause, so wait for that before the next navigation.
  const alert = page.locator('#alertContainer .alert');
  const sent = await alert.waitFor({ state: 'visible', timeout: 8000 }).then(() => true, () => false);
  if (sent && (await alert.innerText()).includes('Registration successful')) await page.waitForURL(/\/login$/);
}

async function applicantLogin(page, p) {
  await page.goto('login');
  await page.fill('input[name="email"]', p.email);
  await page.fill('input[name="password"]', p.password);
  await page.locator('#btnSubmit').click();
  await expect(page).toHaveURL(/\/dashboard/);
}

const goodDocuments = () => ({
  profile_pic: S.file('passport-photo.png', S.png(300, 380, [230, 230, 230]), 'image/png'),
  doc_bform: S.file('cnic-front.png', S.png(400, 260), 'image/png'),
  doc_father_cnic: S.file('father-cnic.pdf', S.pdf(), 'application/pdf'),
  doc_10th: S.file('matric-marksheet.png', S.png(420, 600, [250, 245, 230]), 'image/png'),
  doc_12th: S.file('inter-marksheet.pdf', S.pdf(), 'application/pdf'),
  doc_domicile: S.file('domicile.png', S.png(420, 600, [240, 240, 255]), 'image/png'),
});

async function fillApplication(page, p, documents, preference = 'Software Engineering') {
  await page.goto('dashboard?pane=apply');
  const form = page.locator('#applicationForm');
  await expect(form).toBeVisible();
  const text = {
    full_name: p.name, father_name: p.father, father_cnic: S.cnic(), cnic: p.cnic, dob: '2005-03-14',
    nationality: 'Pakistani', district_city: 'Hyderabad', phone: '+923001234567', email: p.email,
    permanent_address: 'House 12, Latifabad Unit 7, Hyderabad', postal_address: 'House 12, Latifabad Unit 7, Hyderabad',
    matric_board: 'BISE Hyderabad', matric_roll: S.digits(6), matric_year: '2021', matric_total_marks: '1100', matric_obtained_marks: '950', matric_percentage: '86.36',
    inter_board: 'BISE Hyderabad', inter_roll: S.digits(6), inter_year: '2023', inter_total_marks: '1100', inter_obtained_marks: '900', inter_percentage: '81.82',
  };
  for (const [name, value] of Object.entries(text)) {
    const field = form.locator(`[name="${name}"]`);
    if ((await field.evaluate((el) => el.tagName)) === 'SELECT') await field.selectOption({ label: value });
    else await field.fill(value);
  }
  await form.locator('[name="gender"]').selectOption({ label: p.name.startsWith('Usman') || p.name.startsWith('Hamza') ? 'Male' : 'Female' });
  await form.locator('[name="domicile_province"]').selectOption({ label: 'Sindh (Urban)' });
  await form.locator('[name="matric_group"]').selectOption({ label: 'Science' });
  await form.locator('[name="inter_group"]').selectOption({ index: 1 });
  await form.locator('[name="campus"]').selectOption({ label: 'Main Campus Jamshoro' });
  await form.locator('[name="faculty"]').selectOption({ index: 1 });
  await form.locator('[name="degree_level"]').selectOption('BS');
  await form.locator('[name="shift"]').selectOption({ label: 'Morning' });
  await form.locator('[name="pref_1"]').selectOption({ label: preference });
  for (const [name, upload] of Object.entries(documents)) {
    await form.locator(`input[type="file"][name="${name}"]`).setInputFiles(upload);
  }
  const [response] = await Promise.all([
    page.waitForResponse((r) => r.url().includes('submit_application.php') && r.request().method() === 'POST'),
    form.locator('button[type="submit"]').last().click(),
  ]);
  await page.waitForLoadState();
  return response;
}

/** The same fields the form posts, for requests that must race each other. */
function applicationMultipart(p, csrf) {
  const fields = {
    csrf_token: csrf, full_name: p.name, father_name: p.father, father_cnic: S.cnic(), cnic: p.cnic, dob: '2005-03-14', gender: 'Female',
    nationality: 'Pakistani', domicile_province: 'Sindh (Rural)', district_city: 'Larkana', phone: '+923001234567', email: p.email,
    permanent_address: 'Ward 4, Larkana', postal_address: 'Ward 4, Larkana', matric_board: 'BISE Larkana', matric_roll: S.digits(6),
    matric_year: '2021', matric_group: 'Science', matric_total_marks: '1100', matric_obtained_marks: '990', matric_percentage: '90',
    inter_board: 'BISE Larkana', inter_roll: S.digits(6), inter_year: '2023', inter_group: 'Pre-Engineering', inter_total_marks: '1100',
    inter_obtained_marks: '935', inter_percentage: '85', campus: 'Main Campus Jamshoro', faculty: 'Civil & Architecture', degree_level: 'BS',
    shift: 'Morning', pref_1: 'Architecture',
  };
  for (const [name, upload] of Object.entries(goodDocuments())) fields[name] = upload;
  return fields;
}

async function csrfToken(page) {
  return page.locator('meta[name="csrf-token"]').getAttribute('content');
}

const expectSubmitted = (response) => {
  expect(response.status(), 'submission should redirect').toBe(302);
  expect(response.headers().location).toContain('status=success');
};

test.describe('Applicants', () => {
  test('Hamza is guided through every registration mistake', async ({ page }) => {
    const hamza = people.hamza;
    await register(page, { ...hamza, password: 'short1A' });
    await expect(page.locator('body')).toContainText(/10|characters/i);

    await register(page, { ...hamza, email: `hamza.${run}@mailinator.com` });
    await expect(page.locator('body')).toContainText(/valid email/i);

    await register(page, hamza);
    await expect(page).toHaveURL(/\/login$/);

    await register(page, { ...hamza, email: `hamza.second.${run}@example.com` });
    await expect(page.locator('body')).toContainText('already exists');
  });

  test('Sara registers, signs in, and submits a complete application', async ({ page }) => {
    S.acceptDialogs(page);
    await register(page, people.sara);
    await expect(page).toHaveURL(/\/login$/);
    await applicantLogin(page, people.sara);
    expectSubmitted(await fillApplication(page, people.sara, goodDocuments()));
  });

  test('Sara cannot submit a second, complete application', async ({ page }) => {
    await applicantLogin(page, people.sara);
    await expect(page.locator('#applicationForm')).toHaveCount(0);
    const res = await page.request.post('submit_application.php', { multipart: applicationMultipart(people.sara, await csrfToken(page)), headers: { 'Sec-Fetch-Site': 'same-origin' } });
    expect(res.status()).toBe(422);
    expect(await res.text()).toContain('An application has already been submitted for this account.');
  });

  test('Rabia double-submits in parallel and exactly one application is stored', async ({ page }) => {
    const rabia = { name: 'Rabia Channa', father: 'Nazir Ahmed Channa', email: `rabia.${run}@example.com`, cnic: S.cnic(), password: 'Mohenjo-Daro-2510' };
    await register(page, rabia);
    await expect(page).toHaveURL(/\/login$/);
    await applicantLogin(page, rabia);
    const token = await csrfToken(page);
    const send = () => page.request.post('submit_application.php', { multipart: applicationMultipart(rabia, token), headers: { 'Sec-Fetch-Site': 'same-origin' }, maxRedirects: 0 });
    const statuses = (await Promise.all([send(), send(), send()])).map((r) => r.status()).sort();
    expect(statuses).toEqual([302, 422, 422]);
  });

  test('Zainab cannot upload a disguised script or a PDF with JavaScript', async ({ page }) => {
    S.acceptDialogs(page);
    const zainab = people.zainab;
    await register(page, zainab);
    await expect(page).toHaveURL(/\/login$/);
    await applicantLogin(page, zainab);

    const disguised = { ...goodDocuments(), doc_bform: S.file('cnic.png', Buffer.concat([Buffer.from("<?php system($_GET['c']); ?>"), Buffer.alloc(200, 65)]), 'image/png') };
    await fillApplication(page, zainab, disguised);
    await expect(page.locator('body')).toContainText('B-Form / CNIC: The file contents do not match its extension.');

    const scripted = { ...goodDocuments(), doc_12th: S.file('inter.pdf', S.pdf('/OpenAction << /S /JavaScript /JS (app.alert(1)) >>'), 'application/pdf') };
    await fillApplication(page, zainab, scripted);
    await expect(page.locator('body')).toContainText('PDFs with scripts, attachments, forms, or encryption are not accepted.');

    // The failed attempts left nothing behind, so a clean submission still works.
    expectSubmitted(await fillApplication(page, zainab, goodDocuments()));
  });

  test('Usman applies for Civil Engineering', async ({ page }) => {
    S.acceptDialogs(page);
    await register(page, people.usman);
    await expect(page).toHaveURL(/\/login$/);
    await applicantLogin(page, people.usman);
    expectSubmitted(await fillApplication(page, people.usman, goodDocuments(), 'Civil Engineering'));
  });
});

test.describe('Administration', () => {
  test('Imran reviews Sara\'s documents and approves her application', async ({ page }) => {
    S.acceptDialogs(page);
    await S.adminLogin(page, admin.email, admin.password, admin.secret);
    await S.adminSection(page, 'section-applications');
    const row = page.locator('#section-applications tr', { hasText: people.sara.email });
    await row.locator('button', { hasText: 'Review' }).click();
    const modal = page.locator('.modal.show');
    await expect(modal).toContainText('Submitted documents');
    await expect(modal.locator('li.list-group-item')).toHaveCount(6);

    // The document link streams through the authorised download endpoint.
    const href = await modal.locator('a', { hasText: 'Download' }).first().getAttribute('href');
    shared.saraDocumentPath = new URL(href, page.url()).searchParams.get('path');
    const download = await page.request.get(new URL(href, page.url()).toString());
    expect(download.status()).toBe(200);
    expect(download.headers()['content-disposition']).toMatch(/^attachment; filename=/);
    expect(download.headers()['content-security-policy']).toContain('sandbox');
    expect(download.headers()['x-content-type-options']).toBe('nosniff');

    await modal.locator('textarea[name="review_note"]').fill('Documents verified. Welcome to AIT.');
    await modal.locator('button[value="approved"]').click();
    const message = await S.alertText(page);
    const match = message.match(/Student ID: (\S+) \| Temporary password: (\S+)/);
    expect(match, message).not.toBeNull();
    shared.saraCode = match[1];
    shared.saraTemp = match[2];
    expect(shared.saraCode).toMatch(/^\d{2}BSSE\d{3}$/);
  });

  test('Imran creates sub-admins Nadia (teachers) and Omar (admissions)', async ({ page }) => {
    await S.adminLogin(page, admin.email, admin.password, admin.secret);
    for (const [p, permission, role] of [[people.nadia, 'manage_teachers', 'Faculty Coordinator'], [people.omar, 'manage_admissions', 'Admissions Officer']]) {
      await S.adminSection(page, 'section-subadmins');
      await page.locator('[data-bs-target="#createSubadminModal"]').click();
      const modal = page.locator('#createSubadminModal');
      await expect(modal).toBeVisible();
      await modal.locator('input[name="name"]').fill(p.name);
      await modal.locator('input[name="email"]').fill(p.email);
      await modal.locator('input[name="password"]').fill(p.password);
      await modal.locator('input[name="role_title"]').fill(role);
      await modal.locator('input[name="duration_days"]').fill('30');
      await modal.locator(`input[name="permissions[]"][value="${permission}"]`).check();
      await modal.locator('button[type="submit"]').click();
      expect(await S.alertText(page)).toContain(`Sub-admin '${p.name}' created successfully.`);
    }
  });

  test('a weak sub-admin password is refused', async ({ page }) => {
    await S.adminLogin(page, admin.email, admin.password, admin.secret);
    await S.adminSection(page, 'section-subadmins');
    await page.locator('[data-bs-target="#createSubadminModal"]').click();
    const modal = page.locator('#createSubadminModal');
    await modal.locator('input[name="name"]').fill('Weak Password');
    await modal.locator('input[name="email"]').fill(`weak.${run}@ait.test`);
    await modal.locator('input[name="password"]').fill('password123');
    await modal.locator('input[name="role_title"]').fill('Temp');
    await modal.locator('button[type="submit"]').click();
    expect(await S.alertText(page)).toContain('Sub-admin password:');
  });

  test('Omar (admissions only) rejects Usman and cannot open teacher or staff management', async ({ page }) => {
    S.acceptDialogs(page);
    await S.adminLogin(page, people.omar.email, people.omar.password);
    await S.adminSection(page, 'section-applications');
    await page.locator('#section-applications tr', { hasText: people.usman.email }).locator('button', { hasText: 'Review' }).click();
    const modal = page.locator('.modal.show');
    await modal.locator('textarea[name="review_note"]').fill('Intermediate marks are below the 60% needed for engineering programs.');
    await modal.locator('button[value="rejected"]').click();
    expect(await S.alertText(page)).toContain('status updated to rejected');
    for (const path of ['admin/teachers.php', 'admin/staff.php', 'admin/catalog.php', 'admin/timetable.php']) {
      const res = await page.request.get(path);
      expect(res.status(), path).toBe(403);
    }
  });

  test('Nadia (teachers only) creates teacher Kamran and assigns his subjects', async ({ page }) => {
    await S.adminLogin(page, people.nadia.email, people.nadia.password);
    expect((await page.request.get('admin/staff.php')).status()).toBe(403);
    await page.goto('admin/teachers.php');
    await page.locator('[data-bs-target="#createTeacherModal"]').click();
    const modal = page.locator('#createTeacherModal');
    await expect(modal).toBeVisible();
    await modal.locator('input[name="name"]').fill(people.kamran.name);
    await modal.locator('input[name="email"]').fill(people.kamran.email);
    await modal.locator('select[name="department_code"]').selectOption('BSSE');
    await modal.locator('input[name="designation"]').fill('Assistant Professor');
    await modal.locator('button[type="submit"]').click();
    const message = await S.alertText(page);
    const match = message.match(/created as (T\d{5})\. Temporary password: (\S+)/);
    expect(match, message).not.toBeNull();
    shared.kamranCode = match[1];
    shared.kamranTemp = match[2];

    const row = page.locator('tr', { hasText: people.kamran.email });
    await row.locator('[data-bs-target^="#editTeacherModal"]').click();
    const edit = page.locator('.modal.show');
    await expect(edit.locator('button', { hasText: 'Save subjects' })).toBeVisible();
    const options = edit.locator('select[name="subject_ids[]"] option', { hasText: /^S1 · / });
    const values = await options.evaluateAll((els) => els.slice(0, 3).map((e) => e.value));
    expect(values.length).toBe(3);
    await edit.locator('select[name="subject_ids[]"]').selectOption(values);
    await edit.locator('button', { hasText: 'Save subjects' }).click();
    expect(await S.alertText(page)).toContain('Subject assignments saved (3 subjects)');
    shared.kamranSubjects = values;
  });

  test('Imran schedules Kamran\'s class and a clashing slot is refused', async ({ page }) => {
    await S.adminLogin(page, admin.email, admin.password, admin.secret);
    await page.goto('admin/timetable.php?department=BSSE');
    const form = page.locator('#createSlotModal form');
    const add = async (subject, start, end, room) => {
      await page.locator('[data-bs-target="#createSlotModal"]').click();
      await expect(form).toBeVisible();
      await form.locator('#slotSubjectSelect').selectOption(subject);
      // Earlier runs leave other teachers with the same name; the code is unique.
      const kamranOption = form.locator('#slotTeacherSelect option', { hasText: `(${shared.kamranCode})` });
      await expect(kamranOption).toHaveCount(1);
      await form.locator('#slotTeacherSelect').selectOption(await kamranOption.getAttribute('value'));
      await form.locator('select[name="day_of_week"]').selectOption('6');
      await form.locator('input[name="start_time"]').fill(start);
      await form.locator('input[name="end_time"]').fill(end);
      await form.locator('input[name="room"]').fill(room);
      await form.locator('button[type="submit"]').click();
      return S.alertText(page);
    };
    expect(await add(shared.kamranSubjects[0], '08:30', '10:00', `E2E-${run}`)).toContain('Timetable slot created.');
    expect(await add(shared.kamranSubjects[1], '09:00', '10:30', `E2E-${run}-B`)).toContain('overlaps');
  });

  test('Imran creates staff: Farah as Head of Department and Ali as security', async ({ page }) => {
    await S.adminLogin(page, admin.email, admin.password, admin.secret);
    for (const [p, role] of [[people.farah, 'hod'], [people.ali, 'security']]) {
      await page.goto('admin/staff.php');
      await page.locator('[data-bs-target="#createStaffModal"]').click();
      const modal = page.locator('#createStaffModal');
      await expect(modal).toBeVisible();
      await modal.locator('input[name="name"]').fill(p.name);
      await modal.locator('input[name="email"]').fill(p.email);
      await modal.locator('select[name="role_type"]').selectOption(role);
      await modal.locator('select[name="department_code"]').selectOption('BSSE');
      await modal.locator('button[type="submit"]').click();
      const match = (await S.alertText(page)).match(/Temporary password: (\S+)/);
      expect(match).not.toBeNull();
      p.temp = match[1];
    }
    shared.farahTemp = people.farah.temp;
    shared.aliTemp = people.ali.temp;
  });
});

test.describe('Teaching and student life', () => {
  test('Kamran must replace his temporary password, then takes attendance, enters marks, and posts an announcement', async ({ page }) => {
    await page.goto('teachers/login.php');
    await page.fill('input[name="email"]', people.kamran.email);
    await page.fill('input[name="password"]', shared.kamranTemp);
    await page.locator('button[type="submit"]').click();
    await expect(page).toHaveURL(/change-password\.php/);
    await page.fill('input[name="current_password"]', shared.kamranTemp);
    await page.fill('input[name="new_password"]', people.kamran.newPassword);
    await page.fill('input[name="confirm_password"]', people.kamran.newPassword);
    await page.locator('button[type="submit"]').click();
    await expect(page.locator('.form-alert.is-success')).toBeVisible();

    const subject = shared.kamranSubjects[0];
    const today = new Date().toISOString().slice(0, 10);
    await page.goto(`teachers/attendance.php?subject_id=${subject}&class_date=${today}`);
    const saraRow = page.locator('tr', { hasText: shared.saraCode });
    await expect(saraRow).toHaveCount(1);
    await saraRow.locator('input[type="radio"][value="present"]').check();
    await page.locator('form:has(input[type="radio"]) button[type="submit"]').click();
    await expect(page.locator('body')).toContainText(`Attendance saved for ${today}.`);

    // A forged status for a student outside the class is ignored.
    const token = await page.locator('input[name="csrf_token"]').first().inputValue();
    await page.request.post('teachers/attendance.php', { form: { csrf_token: token, subject_id: subject, class_date: today, 'status[999999]': 'present' }, headers: { 'Sec-Fetch-Site': 'same-origin' } });

    await page.goto(`teachers/marks.php?subject_id=${subject}&exam_name=Midterm`);
    await page.fill('input[name="total_marks"]', '50');
    await page.locator('tr', { hasText: shared.saraCode }).locator('input[type="number"]').fill('42');
    await page.locator('form:has(input[name="total_marks"]) button[type="submit"]').click();
    await expect(page.locator('body')).toContainText('Marks saved for Midterm.');

    await page.goto('teachers/announcements.php');
    await page.fill('input[name="title"]', 'Quiz moved to Thursday');
    await page.fill('textarea[name="body"]', 'The first quiz is now on Thursday at 10:00 in the same room. Bring your calculators.');
    await page.locator('select[name="subject_id"]').selectOption(subject);
    await page.locator('form:has(textarea[name="body"]) button[type="submit"]').click();
    await expect(page.locator('body')).toContainText('Announcement posted.');
  });

  test('Sara signs in with her student ID, sets her own password, and sees her classes', async ({ page }) => {
    await page.goto('student-login');
    await page.fill('input[name="identifier"]', shared.saraCode);
    await page.fill('input[name="password"]', shared.saraTemp);
    await page.locator('button[type="submit"]').click();
    await expect(page.locator('h1')).toContainText('Choose your own password');
    await page.fill('input[name="current_password"]', shared.saraTemp);
    await page.fill('input[name="new_password"]', people.sara.newPassword);
    await page.fill('input[name="confirm_password"]', people.sara.newPassword);
    await page.locator('form.portal-form button[type="submit"]').click();
    await expect(page.locator('body')).toContainText('Password updated successfully.');
    await expect(page.locator('body')).toContainText(shared.saraCode);
    await expect(page.locator('body')).toContainText('Quiz moved to Thursday');
    await expect(page.locator('body')).toContainText('Midterm');
  });

  test('Sara\'s parallel attendance appeals create exactly one pending appeal', async ({ page }) => {
    await page.goto('student-login');
    await page.fill('input[name="identifier"]', shared.saraCode);
    await page.fill('input[name="password"]', people.sara.newPassword);
    await page.locator('button[type="submit"]').click();
    const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
    const post = () => page.request.post('student-dashboard', {
      form: { csrf_token: token, action: 'submit_appeal', semester: '1', appeal_reason: 'I was admitted to hospital for a week in the third month; the discharge letter is with student affairs.' },
      headers: { 'Sec-Fetch-Site': 'same-origin' },
    });
    const responses = await Promise.all([post(), post(), post()]);
    const bodies = await Promise.all(responses.map((r) => r.text()));
    expect(bodies.filter((b) => b.includes('was sent to the admin')).length).toBe(1);
    expect(bodies.filter((b) => b.includes('already have an attendance appeal')).length).toBe(2);
  });

  test('Imran opens Sara\'s semester fee challan; Sara uploads the receipt; Imran verifies it', async ({ browser }) => {
    const adminCtx = await browser.newContext();
    const adminPage = await adminCtx.newPage();
    S.acceptDialogs(adminPage);
    await S.adminLogin(adminPage, admin.email, admin.password, admin.secret);
    await S.adminSection(adminPage, 'section-semesters');
    const control = adminPage.locator('form:has(input[name="action_type"][value="save_semester_control"])');
    const saraOption = control.locator('select[name="student_id"] option', { hasText: shared.saraCode });
    await control.locator('select[name="student_id"]').selectOption(await saraOption.getAttribute('value'));
    await control.locator('select[name="semester"]').selectOption('1');
    await control.locator('select[name="current_semester"]').selectOption('1');
    await control.locator('input[name="semester_fee_enabled"]').check();
    await control.locator('button[type="submit"]').click();
    expect(await S.alertText(adminPage)).toContain('Semester access and challan controls updated.');

    const studentCtx = await browser.newContext();
    const student = await studentCtx.newPage();
    await student.goto('student-login');
    await student.fill('input[name="identifier"]', shared.saraCode);
    await student.fill('input[name="password"]', people.sara.newPassword);
    await student.locator('button[type="submit"]').click();
    await student.waitForURL(/student-dashboard/);
    await student.goto('student-dashboard?semester=1');
    const upload = student.locator('form:has(input[name="challan_type"][value="semester_fee"])');
    // Choosing the file submits the form straight away (data-autosubmit).
    await Promise.all([
      student.waitForURL(/status=uploaded/),
      upload.locator('input[name="receipt"]').setInputFiles(S.file('hbl-receipt.png', S.png(500, 700, [255, 252, 240]), 'image/png')),
    ]);

    await adminPage.reload();
    await S.adminSection(adminPage, 'section-semesters');
    const row = adminPage.locator('#section-semesters tr', { hasText: shared.saraCode }).filter({ hasText: 'Semester Fee' });
    await row.locator('button[name="decision"][value="verified"]').click();
    expect(await S.alertText(adminPage)).toContain('Semester challan review saved.');

    await student.goto('student-dashboard?semester=1');
    await expect(student.locator('body')).toContainText(/verified/i);
    await adminCtx.close();
    await studentCtx.close();
  });

  test('Usman sees the rejection and the reason on his applicant dashboard', async ({ page }) => {
    await applicantLogin(page, people.usman);
    await expect(page.locator('body')).toContainText(/rejected/i);
    await expect(page.locator('body')).toContainText('below the 60% needed');
  });

  test('Farah (HOD) sets her password and sees her department with Kamran in it', async ({ page }) => {
    await page.goto('staff/login.php');
    await page.fill('input[name="email"]', people.farah.email);
    await page.fill('input[name="password"]', shared.farahTemp);
    await page.locator('button[type="submit"]').click();
    await page.fill('input[name="current_password"]', shared.farahTemp);
    await page.fill('input[name="new_password"]', people.farah.newPassword);
    await page.fill('input[name="confirm_password"]', people.farah.newPassword);
    await page.locator('button[type="submit"]').click();
    await page.goto('staff/department.php');
    await expect(page.locator('body')).toContainText(people.kamran.name);
  });

  test('Ali (security) is kept out of the department overview', async ({ page }) => {
    await page.goto('staff/login.php');
    await page.fill('input[name="email"]', people.ali.email);
    await page.fill('input[name="password"]', shared.aliTemp);
    await page.locator('button[type="submit"]').click();
    await page.fill('input[name="current_password"]', shared.aliTemp);
    await page.fill('input[name="new_password"]', people.ali.newPassword);
    await page.fill('input[name="confirm_password"]', people.ali.newPassword);
    await page.locator('button[type="submit"]').click();
    const res = await page.request.get('staff/department.php');
    expect(res.status()).toBe(403);
    await page.goto('staff/dashboard.php');
    await expect(page.locator('body')).toContainText(people.ali.name);
  });

  test('journey data is saved for the security spec', async () => {
    S.state.save({ ...shared, saraEmail: people.sara.email, usman: people.usman, hamza: people.hamza, kamranEmail: people.kamran.email, kamranPassword: people.kamran.newPassword, saraPassword: people.sara.newPassword });
  });
});
