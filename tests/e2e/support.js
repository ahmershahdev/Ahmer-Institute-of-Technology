const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const { expect } = require('@playwright/test');

const ROOT = path.resolve(__dirname, '../..');

function env() {
  const file = path.join(ROOT, '.env.docker');
  const values = { ...process.env };
  if (fs.existsSync(file)) {
    for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
      const match = line.match(/^([A-Z0-9_]+)=(.*)$/);
      if (match && values[match[1]] === undefined) values[match[1]] = match[2];
    }
  }
  for (const key of ['E2E_ADMIN_EMAIL', 'E2E_ADMIN_PASSWORD', 'SUPERADMIN_SECRET_KEY']) {
    if (!values[key]) throw new Error(`${key} is missing. Run bin/docker-init.sh and bin/create-admin.php first.`);
  }
  return values;
}

const STATE_FILE = path.join(__dirname, '.journey-state.json');
const state = {
  load() {
    return fs.existsSync(STATE_FILE) ? JSON.parse(fs.readFileSync(STATE_FILE, 'utf8')) : {};
  },
  save(patch) {
    fs.mkdirSync(path.dirname(STATE_FILE), { recursive: true });
    const next = { ...state.load(), ...patch };
    fs.writeFileSync(STATE_FILE, JSON.stringify(next, null, 2));
    return next;
  },
};

const run = Date.now().toString(36);
const digits = (n) => Array.from({ length: n }, () => Math.floor(Math.random() * 10)).join('');
const cnic = () => `4${digits(4)}-${digits(7)}-${digits(1)}`;

function png(width = 240, height = 300, rgb = [11, 119, 114]) {
  const crcTable = Array.from({ length: 256 }, (_, n) => {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    return c >>> 0;
  });
  const crc = (buf) => {
    let c = 0xffffffff;
    for (const byte of buf) c = crcTable[(c ^ byte) & 0xff] ^ (c >>> 8);
    return (c ^ 0xffffffff) >>> 0;
  };
  const chunk = (type, data) => {
    const len = Buffer.alloc(4);
    len.writeUInt32BE(data.length);
    const body = Buffer.concat([Buffer.from(type), data]);
    const sum = Buffer.alloc(4);
    sum.writeUInt32BE(crc(body));
    return Buffer.concat([len, body, sum]);
  };
  const header = Buffer.alloc(13);
  header.writeUInt32BE(width, 0);
  header.writeUInt32BE(height, 4);
  header[8] = 8;
  header[9] = 2;
  const row = Buffer.concat([Buffer.from([0]), Buffer.from(Array.from({ length: width }, () => rgb).flat())]);
  const raw = Buffer.concat(Array.from({ length: height }, () => row));
  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', header),
    chunk('IDAT', zlib.deflateSync(raw)),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}

function pdf(extra = '') {
  return Buffer.from(
    `%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R ${extra} >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n` +
      '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 300 300] >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n' + ' '.repeat(80),
  );
}

const file = (name, buffer, mimeType) => ({ name, buffer, mimeType });

async function solveCaptcha(scope) {
  const label = scope.locator('label.ait-captcha');
  if ((await label.count()) === 0) return;
  const text = await label.first().innerText();
  const [, a, b] = text.match(/(\d+)\s*\+\s*(\d+)/);
  await label.first().locator('input').fill(String(Number(a) + Number(b)));
}

const humanPause = (page, ms = 3200) => page.waitForTimeout(ms);

function acceptDialogs(page) {
  page.on('dialog', (dialog) => dialog.accept());
}

async function adminLogin(page, email, password, secret = '') {
  await page.goto('admin/login.php');
  await page.fill('#emailInput', email);
  await page.fill('#secretKeyInput', secret || 'not-a-super-admin');
  await page.fill('#passwordInput', password);
  await page.locator('#submitBtn').click();
  await expect(page).toHaveURL(/admin\/dashboard\.php/);
}

async function adminSection(page, id) {
  await page.locator(`.nav-tab-link[data-target="${id}"]`).first().click();
  await expect(page.locator(`#${id}`)).toBeVisible();
}

async function alertText(page) {
  const alert = page.locator('.alert, .form-alert').first();
  await expect(alert).toBeVisible();
  return (await alert.innerText()).trim();
}

module.exports = { env, state, run, cnic, digits, png, pdf, file, solveCaptcha, humanPause, acceptDialogs, adminLogin, adminSection, alertText, expect };
