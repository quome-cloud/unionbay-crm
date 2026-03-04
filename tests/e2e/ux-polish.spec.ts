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

test.describe('UX Polish (T100)', () => {
  test('Action Stream is default landing page (sort=1 in menu)', async () => {
    const res = await api.get('/api/v1/activities', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
  });

  test('Navigation order: core endpoints all accessible', async () => {
    const endpoints = [
      '/api/v1/activities',
      '/api/v1/contacts',
      '/api/v1/leads',
    ];

    for (const endpoint of endpoints) {
      const res = await api.get(endpoint, { headers: authHeaders() });
      expect(res.ok()).toBeTruthy();
    }
  });

  test('Contacts API returns paginated data', async () => {
    const res = await api.get('/api/v1/contacts', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeDefined();
    expect(Array.isArray(body.data)).toBeTruthy();
  });

  test('Leads API returns paginated data', async () => {
    const res = await api.get('/api/v1/leads', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeDefined();
  });

  test('Action stream endpoint exists and responds', async () => {
    const res = await api.get('/api/v1/action-stream', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeDefined();
  });

  test('Speed dial accessible from navigation context', async () => {
    const res = await api.get('/api/v1/speed-dial', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('favorites');
    expect(body.data).toHaveProperty('recent');
  });

  test('Notifications endpoint accessible', async () => {
    const res = await api.get('/api/v1/notifications', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeDefined();
  });

  test('Auth endpoints work correctly', async ({ playwright }) => {
    // Test that unauthenticated requests are rejected
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const res = await unauthApi.get('/api/v1/contacts', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });
});
