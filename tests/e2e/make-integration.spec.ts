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

test.describe('Make.com Integration (T067)', () => {
  const makeWebhookBase = 'https://hook.us1.make.com/t067test';
  const createdSubscriptionIds: number[] = [];

  // ---------------------------------------------------------------
  // Module definitions / trigger listing
  // ---------------------------------------------------------------

  test('GET /webhooks/events lists available trigger modules', async () => {
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

    // Every event should include a human-readable description
    for (const evt of body.data) {
      expect(evt).toHaveProperty('event');
      expect(evt).toHaveProperty('description');
      expect(typeof evt.description).toBe('string');
      expect(evt.description.length).toBeGreaterThan(0);
    }
  });

  // ---------------------------------------------------------------
  // Webhook subscription creation for Make.com scenarios
  // ---------------------------------------------------------------

  test('POST /webhooks/subscribe creates new_contact subscription for Make', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'new_contact',
        target_url: `${makeWebhookBase}/new-contact`,
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data).toHaveProperty('id');
    expect(body.data.event).toBe('new_contact');
    expect(body.data.target_url).toBe(`${makeWebhookBase}/new-contact`);
    expect(body.data.is_active).toBe(true);
    expect(body.data.failure_count).toBe(0);
    createdSubscriptionIds.push(body.data.id);
  });

  test('POST /webhooks/subscribe creates new_lead subscription for Make', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'new_lead',
        target_url: `${makeWebhookBase}/new-lead`,
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.event).toBe('new_lead');
    createdSubscriptionIds.push(body.data.id);
  });

  test('POST /webhooks/subscribe creates lead_stage_changed subscription for Make', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'lead_stage_changed',
        target_url: `${makeWebhookBase}/lead-stage-changed`,
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.event).toBe('lead_stage_changed');
    createdSubscriptionIds.push(body.data.id);
  });

  test('POST /webhooks/subscribe creates deal_won subscription for Make', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'deal_won',
        target_url: `${makeWebhookBase}/deal-won`,
      },
    });
    expect(res.status()).toBe(201);
    createdSubscriptionIds.push((await res.json()).data.id);
  });

  test('POST /webhooks/subscribe creates deal_lost subscription for Make', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'deal_lost',
        target_url: `${makeWebhookBase}/deal-lost`,
      },
    });
    expect(res.status()).toBe(201);
    createdSubscriptionIds.push((await res.json()).data.id);
  });

  test('POST /webhooks/subscribe creates new_activity subscription for Make', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'new_activity',
        target_url: `${makeWebhookBase}/new-activity`,
      },
    });
    expect(res.status()).toBe(201);
    createdSubscriptionIds.push((await res.json()).data.id);
  });

  test('POST /webhooks/subscribe creates email_received subscription for Make', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'email_received',
        target_url: `${makeWebhookBase}/email-received`,
      },
    });
    expect(res.status()).toBe(201);
    createdSubscriptionIds.push((await res.json()).data.id);
  });

  // ---------------------------------------------------------------
  // Listing and retrieving subscriptions
  // ---------------------------------------------------------------

  test('GET /webhooks lists Make subscriptions alongside others', async () => {
    const res = await api.get('/api/v1/webhooks', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);

    // At least the 7 Make subscriptions we just created
    const makeSubs = body.data.filter((s: any) =>
      s.target_url.startsWith(makeWebhookBase)
    );
    expect(makeSubs.length).toBe(7);
  });

  test('GET /webhooks/:id retrieves a specific Make subscription', async () => {
    const id = createdSubscriptionIds[0];
    expect(id).toBeTruthy();

    const res = await api.get(`/api/v1/webhooks/${id}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.id).toBe(id);
    expect(body.data.event).toBe('new_contact');
    expect(body.data.target_url).toBe(`${makeWebhookBase}/new-contact`);
    expect(body.data.is_active).toBe(true);
    expect(body.data).toHaveProperty('created_at');
  });

  // ---------------------------------------------------------------
  // Trigger event testing / webhook payload validation
  // ---------------------------------------------------------------

  test('POST /webhooks/:id/test sends sample new_contact payload', async () => {
    const id = createdSubscriptionIds[0]; // new_contact
    expect(id).toBeTruthy();

    const res = await api.post(`/api/v1/webhooks/${id}/test`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('success');
    // The target URL is fake so delivery will likely fail, but the
    // endpoint itself must return 200 with a structured result.
    expect(typeof body.data.success).toBe('boolean');
  });

  test('POST /webhooks/:id/test sends sample new_lead payload', async () => {
    const id = createdSubscriptionIds[1]; // new_lead
    expect(id).toBeTruthy();

    const res = await api.post(`/api/v1/webhooks/${id}/test`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('success');
    expect(typeof body.data.success).toBe('boolean');
  });

  test('POST /webhooks/:id/test sends sample lead_stage_changed payload', async () => {
    const id = createdSubscriptionIds[2]; // lead_stage_changed
    expect(id).toBeTruthy();

    const res = await api.post(`/api/v1/webhooks/${id}/test`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('success');
  });

  test('POST /webhooks/:id/test returns 404 for non-existent subscription', async () => {
    const res = await api.post('/api/v1/webhooks/999999/test', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  // ---------------------------------------------------------------
  // Webhook payload structure validation via events endpoint
  // ---------------------------------------------------------------

  test('event descriptions match expected trigger semantics', async () => {
    const res = await api.get('/api/v1/webhooks/events', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    const eventMap = new Map(
      body.data.map((e: any) => [e.event, e.description])
    );

    // Each event description should convey when the trigger fires
    expect(eventMap.get('new_contact')).toMatch(/contact.*created/i);
    expect(eventMap.get('new_lead')).toMatch(/lead.*created/i);
    expect(eventMap.get('lead_stage_changed')).toMatch(/stage/i);
    expect(eventMap.get('deal_won')).toMatch(/won/i);
    expect(eventMap.get('deal_lost')).toMatch(/lost/i);
    expect(eventMap.get('new_activity')).toMatch(/activity/i);
    expect(eventMap.get('email_received')).toMatch(/email/i);
  });

  // ---------------------------------------------------------------
  // Validation / negative tests
  // ---------------------------------------------------------------

  test('POST /webhooks/subscribe rejects invalid event type', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'contact_deleted',
        target_url: `${makeWebhookBase}/invalid`,
      },
    });
    expect(res.status()).toBe(422);
  });

  test('POST /webhooks/subscribe rejects invalid target_url', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'new_contact',
        target_url: 'not-a-valid-url',
      },
    });
    expect(res.status()).toBe(422);
  });

  test('POST /webhooks/subscribe rejects missing target_url', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        event: 'new_contact',
      },
    });
    expect(res.status()).toBe(422);
  });

  test('POST /webhooks/subscribe rejects missing event', async () => {
    const res = await api.post('/api/v1/webhooks/subscribe', {
      headers: authHeaders(),
      data: {
        target_url: `${makeWebhookBase}/missing-event`,
      },
    });
    expect(res.status()).toBe(422);
  });

  // ---------------------------------------------------------------
  // Authentication requirements
  // ---------------------------------------------------------------

  test('GET /webhooks requires authentication', async ({ playwright }) => {
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const res = await unauthApi.get('/api/v1/webhooks', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });

  test('POST /webhooks/subscribe requires authentication', async ({ playwright }) => {
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const res = await unauthApi.post('/api/v1/webhooks/subscribe', {
      headers: { Accept: 'application/json' },
      data: {
        event: 'new_contact',
        target_url: `${makeWebhookBase}/unauth`,
      },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });

  test('DELETE /webhooks/:id requires authentication', async ({ playwright }) => {
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const id = createdSubscriptionIds[0];
    const res = await unauthApi.delete(`/api/v1/webhooks/${id}`, {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });

  test('POST /webhooks/:id/test requires authentication', async ({ playwright }) => {
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const id = createdSubscriptionIds[0];
    const res = await unauthApi.post(`/api/v1/webhooks/${id}/test`, {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });

  test('GET /webhooks/events requires authentication', async ({ playwright }) => {
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const res = await unauthApi.get('/api/v1/webhooks/events', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });

  // ---------------------------------------------------------------
  // Deletion flow
  // ---------------------------------------------------------------

  test('DELETE /webhooks/:id removes a Make subscription', async () => {
    const id = createdSubscriptionIds[0];
    expect(id).toBeTruthy();

    const res = await api.delete(`/api/v1/webhooks/${id}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
  });

  test('DELETE /webhooks/:id returns 404 for already-deleted subscription', async () => {
    const id = createdSubscriptionIds[0];
    const res = await api.delete(`/api/v1/webhooks/${id}`, {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('GET /webhooks/:id returns 404 for deleted subscription', async () => {
    const id = createdSubscriptionIds[0];
    const res = await api.get(`/api/v1/webhooks/${id}`, {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('POST /webhooks/:id/test returns 404 for deleted subscription', async () => {
    const id = createdSubscriptionIds[0];
    const res = await api.post(`/api/v1/webhooks/${id}/test`, {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  // ---------------------------------------------------------------
  // Cleanup: remove all remaining test subscriptions
  // ---------------------------------------------------------------

  test('cleanup: remove remaining Make test subscriptions', async () => {
    const res = await api.get('/api/v1/webhooks', {
      headers: authHeaders(),
    });
    const body = await res.json();
    for (const sub of body.data) {
      if (sub.target_url.startsWith(makeWebhookBase)) {
        await api.delete(`/api/v1/webhooks/${sub.id}`, {
          headers: authHeaders(),
        });
      }
    }

    // Verify all Make test subscriptions are gone
    const verify = await api.get('/api/v1/webhooks', {
      headers: authHeaders(),
    });
    const verifyBody = await verify.json();
    const remaining = verifyBody.data.filter((s: any) =>
      s.target_url.startsWith(makeWebhookBase)
    );
    expect(remaining.length).toBe(0);
  });
});
