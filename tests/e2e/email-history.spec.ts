import { test, expect, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;

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

test.describe('Email History Import (T050)', () => {
  let accountId: number;

  test('setup: create email account', async () => {
    const res = await api.post('/api/v1/email-accounts', {
      headers: authHeaders(),
      data: {
        email_address: 'history-test@example.com',
        imap_host: 'imap.example.com',
        imap_username: 'history-test@example.com',
        imap_password: 'testpass',
        smtp_host: 'smtp.example.com',
        smtp_username: 'history-test@example.com',
        smtp_password: 'testpass',
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    accountId = body.data.id;
  });

  test('POST /email-accounts/:id/import-history starts import with default 30 days', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.post(`/api/v1/email-accounts/${accountId}/import-history`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.account_id).toBe(accountId);
    expect(body.data.days).toBe(30);
    expect(body.data.status).toBe('completed');
    expect(body.message).toContain('import');
  });

  test('POST /email-accounts/:id/import-history accepts 60 days', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.post(`/api/v1/email-accounts/${accountId}/import-history`, {
      headers: authHeaders(),
      data: { days: 60 },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.days).toBe(60);
  });

  test('POST /email-accounts/:id/import-history accepts 90 days', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.post(`/api/v1/email-accounts/${accountId}/import-history`, {
      headers: authHeaders(),
      data: { days: 90 },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.days).toBe(90);
  });

  test('POST /email-accounts/:id/import-history rejects invalid days', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.post(`/api/v1/email-accounts/${accountId}/import-history`, {
      headers: authHeaders(),
      data: { days: 45 },
    });
    expect(res.status()).toBe(422);
  });

  test('GET /email-accounts/:id/import-history returns status', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.get(`/api/v1/email-accounts/${accountId}/import-history`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.account_id).toBe(accountId);
    expect(body.data.status).toBe('completed');
    expect(body.data.days).toBe(90); // last import was 90 days
    expect(body.data).toHaveProperty('total');
    expect(body.data).toHaveProperty('processed');
    expect(body.data).toHaveProperty('started_at');
    expect(body.data).toHaveProperty('completed_at');
  });

  test('GET /email-accounts/:id includes history_import_status', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.get(`/api/v1/email-accounts/${accountId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('history_import_status');
    expect(body.data).toHaveProperty('history_import_days');
  });

  test('POST /email-accounts/:id/import-history 404 for non-existent account', async () => {
    const res = await api.post('/api/v1/email-accounts/999999/import-history', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('GET /email-accounts/:id/import-history 404 for non-existent account', async () => {
    const res = await api.get('/api/v1/email-accounts/999999/import-history', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('import-history requires authentication', async ({ playwright }) => {
    expect(accountId).toBeTruthy();
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const res = await unauthApi.post(`/api/v1/email-accounts/${accountId}/import-history`, {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });

  test('POST /email-accounts/:id/import-history rejects disabled account', async () => {
    expect(accountId).toBeTruthy();
    // Disable the account first
    await api.put(`/api/v1/email-accounts/${accountId}`, {
      headers: authHeaders(),
      data: { status: 'disabled' },
    });
    const res = await api.post(`/api/v1/email-accounts/${accountId}/import-history`, {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(422);
    // Re-enable
    await api.put(`/api/v1/email-accounts/${accountId}`, {
      headers: authHeaders(),
      data: { status: 'active' },
    });
  });

  test('cleanup: delete test account', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.delete(`/api/v1/email-accounts/${accountId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
  });
});
