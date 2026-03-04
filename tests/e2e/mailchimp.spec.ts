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

  // Create a test contact
  const ts = Date.now();
  const c = await api.post('/api/v1/contacts', {
    headers: authHeaders(),
    data: {
      name: `Mailchimp Test ${ts}`,
      emails: [{ value: `mc-${ts}@example.com`, label: 'work' }],
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

test.describe('Mailchimp Integration - Status', () => {
  test('GET /integrations/mailchimp/status returns disconnected by default', async () => {
    const res = await api.get('/api/v1/integrations/mailchimp/status', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('connected');
    expect(body.data.connected).toBe(false);
  });
});

test.describe('Mailchimp Integration - Connect', () => {
  test('POST /integrations/mailchimp/connect rejects missing API key', async () => {
    const res = await api.post('/api/v1/integrations/mailchimp/connect', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/mailchimp/connect rejects invalid API key format', async () => {
    const res = await api.post('/api/v1/integrations/mailchimp/connect', {
      headers: authHeaders(),
      data: { api_key: 'invalid-key-no-dc' },
    });
    // Should fail validation or Mailchimp ping
    expect([422, 502].includes(res.status()) || !res.ok()).toBeTruthy();
  });

  test('POST /integrations/mailchimp/disconnect works even when not connected', async () => {
    const res = await api.post('/api/v1/integrations/mailchimp/disconnect', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.message).toContain('disconnected');
  });
});

test.describe('Mailchimp Integration - Audiences', () => {
  test('GET /integrations/mailchimp/audiences returns error when not connected', async () => {
    // Ensure disconnected
    await api.post('/api/v1/integrations/mailchimp/disconnect', {
      headers: authHeaders(),
    });

    const res = await api.get('/api/v1/integrations/mailchimp/audiences', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body.message).toContain('not connected');
  });
});

test.describe('Mailchimp Integration - Subscribe/Unsubscribe', () => {
  test('POST /integrations/mailchimp/subscribe validates required fields', async () => {
    const res = await api.post('/api/v1/integrations/mailchimp/subscribe', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });

  test('POST /integrations/mailchimp/subscribe rejects when not connected', async () => {
    const res = await api.post('/api/v1/integrations/mailchimp/subscribe', {
      headers: authHeaders(),
      data: {
        contact_id: contactId,
        audience_id: 'test-audience',
      },
    });
    expect(res.status()).toBe(422);
    const body = await res.json();
    expect(body.message).toContain('not connected');
  });

  test('POST /integrations/mailchimp/unsubscribe validates required fields', async () => {
    const res = await api.post('/api/v1/integrations/mailchimp/unsubscribe', {
      headers: authHeaders(),
      data: {},
    });
    expect(res.status()).toBe(422);
  });
});

test.describe('Mailchimp Integration - Contact Status', () => {
  test('GET /integrations/mailchimp/contacts/{id}/status returns empty for new contact', async () => {
    const res = await api.get(
      `/api/v1/integrations/mailchimp/contacts/${contactId}/status`,
      { headers: authHeaders() }
    );
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBe(0);
  });

  test('GET /integrations/mailchimp/contacts/{id}/campaigns rejects when not connected', async () => {
    const res = await api.get(
      `/api/v1/integrations/mailchimp/contacts/${contactId}/campaigns`,
      { headers: authHeaders() }
    );
    expect(res.status()).toBe(422);
  });
});

test.describe('Mailchimp Integration - Auth Required', () => {
  test('endpoints require authentication', async () => {
    const res = await api.get('/api/v1/integrations/mailchimp/status', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
  });
});
