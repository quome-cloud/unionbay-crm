import { test, expect, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;
let contactId: number;

test.beforeAll(async ({ playwright }) => {
  api = await playwright.request.newContext({ baseURL: BASE });

  const login = await api.post('/api/v1/auth/login', {
    data: { email: 'admin@example.com', password: 'admin123' },
  });
  expect(login.ok()).toBeTruthy();
  const body = await login.json();
  token = body.token || body.data?.token;

  const ts = Date.now();
  const c = await api.post('/api/v1/contacts', {
    headers: authHeaders(),
    data: {
      name: `Xero Test ${ts}`,
      emails: [{ value: `xero-${ts}@example.com`, label: 'work' }],
    },
  });
  expect(c.status()).toBe(201);
  contactId = (await c.json()).data.id;
});

test.afterAll(async () => {
  await api.dispose();
});

function authHeaders() {
  return { Authorization: `Bearer ${token}`, Accept: 'application/json' };
}

test.describe('Xero Integration - Status', () => {
  test('GET /integrations/xero/status returns disconnected by default', async () => {
    const res = await api.get('/api/v1/integrations/xero/status', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.connected).toBe(false);
  });
});

test.describe('Xero Integration - OAuth', () => {
  test('POST /integrations/xero/auth-url validates required fields', async () => {
    const res = await api.post('/api/v1/integrations/xero/auth-url', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/xero/auth-url returns authorization URL', async () => {
    const res = await api.post('/api/v1/integrations/xero/auth-url', {
      headers: authHeaders(),
      data: {
        client_id: 'test-xero-client',
        client_secret: 'test-xero-secret',
        redirect_uri: 'http://localhost:8190/api/v1/integrations/xero/callback',
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.auth_url).toContain('login.xero.com');
    expect(body.data.auth_url).toContain('test-xero-client');
  });

  test('POST /integrations/xero/callback validates code and tenant_id', async () => {
    const res = await api.post('/api/v1/integrations/xero/callback', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/xero/disconnect works', async () => {
    const res = await api.post('/api/v1/integrations/xero/disconnect', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.message).toContain('disconnected');
  });
});

test.describe('Xero Integration - Invoices', () => {
  test('POST /integrations/xero/invoices validates required fields', async () => {
    const res = await api.post('/api/v1/integrations/xero/invoices', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/xero/invoices rejects when not connected', async () => {
    const res = await api.post('/api/v1/integrations/xero/invoices', {
      headers: authHeaders(),
      data: {
        contact_id: contactId,
        line_items: [{ description: 'Service', amount: 500 }],
      },
    });
    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body.message).toContain('not connected');
  });
});

test.describe('Xero Integration - Contact Sync', () => {
  test('POST /integrations/xero/sync-contact validates contact_id', async () => {
    const res = await api.post('/api/v1/integrations/xero/sync-contact', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/xero/sync-contact rejects when not connected', async () => {
    const res = await api.post('/api/v1/integrations/xero/sync-contact', {
      headers: authHeaders(),
      data: { contact_id: contactId },
    });
    expect(res.status()).toBe(422);
  });
});

test.describe('Xero Integration - Contact Syncs', () => {
  test('GET /integrations/xero/contacts/{id}/syncs returns empty', async () => {
    const res = await api.get(
      `/api/v1/integrations/xero/contacts/${contactId}/syncs`,
      { headers: authHeaders() }
    );
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBe(0);
  });
});
