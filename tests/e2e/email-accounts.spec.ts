import { test, expect, Page, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;

async function loginPage(page: Page) {
  await page.goto(`${BASE}/admin/login`);
  await page.waitForLoadState('networkidle');
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'admin123');
  await page.click('.primary-button');
  await page.waitForURL('**/admin/dashboard', { timeout: 15000 });
  await page.waitForLoadState('networkidle');
}

function authHeaders() {
  return { Authorization: `Bearer ${token}`, Accept: 'application/json' };
}

test.beforeAll(async ({ playwright }) => {
  api = await playwright.request.newContext({ baseURL: BASE });
  const login = await api.post('/api/v1/auth/login', {
    data: { email: 'admin@example.com', password: 'admin123' },
  });
  expect(login.ok()).toBeTruthy();
  const body = await login.json();
  token = body.token || body.data?.token;
});

test.afterAll(async () => {
  await api.dispose();
});

test.describe('Email Accounts - UI', () => {
  test('email accounts page loads', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/email-accounts`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const pageEl = page.locator('[data-testid="email-accounts-page"]');
    await expect(pageEl).toBeVisible();
  });

  test('add email account button is visible', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/email-accounts`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const addBtn = page.locator('[data-testid="email-accounts-add-btn"]');
    await expect(addBtn).toBeVisible();
  });

  test('clicking add opens modal with provider selection', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/email-accounts`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    await page.locator('[data-testid="email-accounts-add-btn"]').click();
    await page.waitForTimeout(1000);

    const modalTitle = page.locator('[data-testid="email-account-modal-title"]');
    await expect(modalTitle).toBeVisible();
    await expect(modalTitle).toHaveText('Add Email Account');

    const providerSelect = page.locator('[data-testid="email-account-provider-select"]');
    await expect(providerSelect).toBeVisible();
  });

  test('modal has IMAP and SMTP form sections', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/email-accounts`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    await page.locator('[data-testid="email-accounts-add-btn"]').click();
    await page.waitForTimeout(1000);

    await expect(page.locator('[data-testid="email-account-imap-section"]')).toBeVisible();
    await expect(page.locator('[data-testid="email-account-smtp-section"]')).toBeVisible();
    await expect(page.locator('[data-testid="email-account-form-email"]')).toBeVisible();
    await expect(page.locator('[data-testid="email-account-form-imap-host"]')).toBeVisible();
    await expect(page.locator('[data-testid="email-account-form-smtp-host"]')).toBeVisible();
    await expect(page.locator('[data-testid="email-account-form-sync-days"]')).toBeVisible();
  });

  test('cancel button closes modal', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/email-accounts`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    await page.locator('[data-testid="email-accounts-add-btn"]').click();
    await page.waitForTimeout(1000);

    const modalTitle = page.locator('[data-testid="email-account-modal-title"]');
    await expect(modalTitle).toBeVisible();

    await page.locator('[data-testid="email-account-form-cancel"]').click();
    await page.waitForTimeout(500);

    await expect(modalTitle).not.toBeVisible();
  });

  test('shows empty state or account list', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/email-accounts`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    const empty = page.locator('[data-testid="email-accounts-empty"]');
    const list = page.locator('[data-testid="email-accounts-list"]');

    const hasEmpty = await empty.isVisible().catch(() => false);
    const hasList = await list.isVisible().catch(() => false);

    expect(hasEmpty || hasList).toBeTruthy();
  });
});

test.describe('Email Accounts - API', () => {
  let testAccountId: number | null = null;

  test('POST /email-accounts creates an account', async () => {
    const res = await api.post('/api/v1/email-accounts', {
      headers: authHeaders(),
      data: {
        email_address: 'test-e2e@example.com',
        display_name: 'E2E Test Account',
        provider: 'custom',
        imap_host: 'imap.example.com',
        imap_port: 993,
        imap_encryption: 'ssl',
        imap_username: 'test-e2e@example.com',
        imap_password: 'testpassword123',
        smtp_host: 'smtp.example.com',
        smtp_port: 587,
        smtp_encryption: 'tls',
        smtp_username: 'test-e2e@example.com',
        smtp_password: 'testpassword123',
        sync_days: 30,
      },
    });

    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data).toHaveProperty('id');
    expect(body.data.email_address).toBe('test-e2e@example.com');
    expect(body.data.provider).toBe('custom');
    expect(body.data.status).toBe('active');
    testAccountId = body.data.id;
  });

  test('GET /email-accounts returns list', async () => {
    const res = await api.get('/api/v1/email-accounts', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
  });

  test('GET /email-accounts/:id returns single account', async () => {
    if (!testAccountId) return;

    const res = await api.get(`/api/v1/email-accounts/${testAccountId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.id).toBe(testAccountId);
    expect(body.data.email_address).toBe('test-e2e@example.com');
    // Passwords should not be in response
    expect(body.data).not.toHaveProperty('imap_password');
    expect(body.data).not.toHaveProperty('smtp_password');
  });

  test('PUT /email-accounts/:id updates account', async () => {
    if (!testAccountId) return;

    const res = await api.put(`/api/v1/email-accounts/${testAccountId}`, {
      headers: authHeaders(),
      data: {
        display_name: 'Updated E2E Account',
        sync_days: 60,
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.display_name).toBe('Updated E2E Account');
    expect(body.data.sync_days).toBe(60);
  });

  test('POST /email-accounts/:id/test tests connection', async () => {
    if (!testAccountId) return;

    const res = await api.post(`/api/v1/email-accounts/${testAccountId}/test`, {
      headers: authHeaders(),
    });
    // The test endpoint always returns 200 with imap_ok/smtp_ok booleans
    // Connection to example.com will fail, but the endpoint itself should work
    const body = await res.json();
    expect(body.data).toHaveProperty('imap_ok');
    expect(body.data).toHaveProperty('smtp_ok');
    expect(body.data).toHaveProperty('status');
    // With fake credentials, both should fail
    expect(body.data.imap_ok).toBe(false);
    expect(body.data.smtp_ok).toBe(false);
  });

  test('GET /email-accounts/:id/status returns sync status', async () => {
    if (!testAccountId) return;

    const res = await api.get(`/api/v1/email-accounts/${testAccountId}/status`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('status');
    expect(body.data).toHaveProperty('email_count');
    expect(typeof body.data.email_count).toBe('number');
  });

  test('DELETE /email-accounts/:id removes account', async () => {
    if (!testAccountId) return;

    const res = await api.delete(`/api/v1/email-accounts/${testAccountId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();

    // Verify it's gone
    const getRes = await api.get(`/api/v1/email-accounts/${testAccountId}`, {
      headers: authHeaders(),
    });
    expect(getRes.status()).toBe(404);
  });

  test('email accounts require authentication', async ({ playwright }) => {
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const res = await unauthApi.get('/api/v1/email-accounts', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });

  test('validates required fields on create', async () => {
    const res = await api.post('/api/v1/email-accounts', {
      headers: authHeaders(),
      data: {
        email_address: 'incomplete@example.com',
      },
    });
    expect(res.status()).toBe(422);
  });
});
