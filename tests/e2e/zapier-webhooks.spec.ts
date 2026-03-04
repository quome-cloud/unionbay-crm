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

test.describe('Zapier Webhook Integration (T066)', () => {
  let subscriptionId: number;

  test('GET /webhooks/events lists available events', async () => {
    const res = await api.get('/api/v1/webhooks/events', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBe(7);

    const eventNames = body.data.map((e: any) => e.event);
    expect(eventNames).toContain('new_contact');
    expect(eventNames).toContain('new_lead');
    expect(eventNames).toContain('lead_stage_changed');
    expect(eventNames).toContain('deal_won');
    expect(eventNames).toContain('deal_lost');
    expect(eventNames).toContain('new_activity');
    expect(eventNames).toContain('email_received');
  });

  test('POST /webhooks/subscribe creates new_contact subscription', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'new_contact',
        target_url: 'https://hooks.zapier.com/hooks/catch/12345/test/',
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data).toHaveProperty('id');
    expect(body.data.event).toBe('new_contact');
    expect(body.data.target_url).toBe('https://hooks.zapier.com/hooks/catch/12345/test/');
    expect(body.data.is_active).toBe(true);
    subscriptionId = body.data.id;
  });

  test('POST /webhooks/subscribe creates new_lead subscription', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'new_lead',
        target_url: 'https://hooks.zapier.com/hooks/catch/12345/leads/',
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.event).toBe('new_lead');
  });

  test('POST /webhooks/subscribe creates lead_stage_changed subscription', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'lead_stage_changed',
        target_url: 'https://hooks.zapier.com/hooks/catch/12345/stages/',
      },
    });
    expect(res.status()).toBe(201);
  });

  test('POST /webhooks/subscribe creates deal_won subscription', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'deal_won',
        target_url: 'https://hooks.zapier.com/hooks/catch/12345/won/',
      },
    });
    expect(res.status()).toBe(201);
  });

  test('POST /webhooks/subscribe creates deal_lost subscription', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'deal_lost',
        target_url: 'https://hooks.zapier.com/hooks/catch/12345/lost/',
      },
    });
    expect(res.status()).toBe(201);
  });

  test('POST /webhooks/subscribe creates new_activity subscription', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'new_activity',
        target_url: 'https://hooks.zapier.com/hooks/catch/12345/activities/',
      },
    });
    expect(res.status()).toBe(201);
  });

  test('POST /webhooks/subscribe creates email_received subscription', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'email_received',
        target_url: 'https://hooks.zapier.com/hooks/catch/12345/emails/',
      },
    });
    expect(res.status()).toBe(201);
  });

  test('GET /webhooks lists all subscriptions', async () => {
    const res = await api.get('/api/v1/webhooks', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBeGreaterThanOrEqual(7);
  });

  test('GET /webhooks/:id shows specific subscription', async () => {
    expect(subscriptionId).toBeTruthy();
    const res = await api.get(`/api/v1/webhooks/${subscriptionId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.id).toBe(subscriptionId);
    expect(body.data.event).toBe('new_contact');
  });

  test('POST /webhooks/subscribe validates event type', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'invalid_event',
        target_url: 'https://hooks.zapier.com/hooks/catch/12345/test/',
      },
    });
    expect(res.status()).toBe(422);
  });

  test('POST /webhooks/subscribe validates target_url', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'new_contact',
        target_url: 'not-a-url',
      },
    });
    expect(res.status()).toBe(422);
  });

  test('POST /webhooks/subscribe requires target_url', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'new_contact',
      },
    });
    expect(res.status()).toBe(422);
  });

  test('DELETE /webhooks/:id removes subscription', async () => {
    expect(subscriptionId).toBeTruthy();
    const res = await api.delete(`/api/v1/webhooks/${subscriptionId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
  });

  test('DELETE /webhooks/:id 404 for already-deleted', async () => {
    expect(subscriptionId).toBeTruthy();
    const res = await api.delete(`/api/v1/webhooks/${subscriptionId}`, {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('GET /webhooks/:id 404 for deleted subscription', async () => {
    expect(subscriptionId).toBeTruthy();
    const res = await api.get(`/api/v1/webhooks/${subscriptionId}`, {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('webhooks require authentication', async ({ playwright }) => {
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const res = await unauthApi.get('/api/v1/webhooks', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });

  test('cleanup: remove remaining test subscriptions', async () => {
    const res = await api.get('/api/v1/webhooks', {
      headers: authHeaders(),
    });
    const body = await res.json();
    for (const sub of body.data) {
      if (sub.target_url.includes('hooks.zapier.com/hooks/catch/12345')) {
        await api.delete(`/api/v1/webhooks/${sub.id}`, {
          headers: authHeaders(),
        });
      }
    }
  });
});
