import { test, expect, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;

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

function authHeaders() {
  return { Authorization: `Bearer ${token}`, Accept: 'application/json' };
}

test.describe('Broadcast Events', () => {
  test('GET /broadcast/channels returns available channels', async () => {
    const res = await api.get('/api/v1/broadcast/channels', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('private');
    expect(body.data).toHaveProperty('presence');
    expect(body.data).toHaveProperty('config');
    expect(body.data.private).toContain('crm.contacts');
    expect(body.data.private).toContain('crm.leads');
    expect(body.data.presence).toContain('crm.team');
    expect(body.data.config).toHaveProperty('key');
    expect(body.data.config).toHaveProperty('host');
    expect(body.data.config).toHaveProperty('port');
  });

  test('POST /broadcast/test fires notification event', async () => {
    const res = await api.post('/api/v1/broadcast/test', {
      headers: authHeaders(),
      data: { event: 'notification' },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.event).toBe('notification');
    expect(body.data.fired).toBe(true);
  });

  test('POST /broadcast/test fires contact updated event', async () => {
    const res = await api.post('/api/v1/broadcast/test', {
      headers: authHeaders(),
      data: {
        event: 'contact',
        data: { contact_id: 1, action: 'updated' },
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.event).toBe('contact');
    expect(body.data.fired).toBe(true);
  });

  test('POST /broadcast/test fires lead stage changed event', async () => {
    const res = await api.post('/api/v1/broadcast/test', {
      headers: authHeaders(),
      data: {
        event: 'lead',
        data: { lead_id: 1, pipeline_id: 1, from_stage_id: 1, to_stage_id: 2 },
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.event).toBe('lead');
    expect(body.data.fired).toBe(true);
  });

  test('POST /broadcast/test fires email received event', async () => {
    const res = await api.post('/api/v1/broadcast/test', {
      headers: authHeaders(),
      data: {
        event: 'email',
        data: { email_id: 1 },
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.event).toBe('email');
    expect(body.data.fired).toBe(true);
  });

  test('POST /broadcast/test fires action stream updated event', async () => {
    const res = await api.post('/api/v1/broadcast/test', {
      headers: authHeaders(),
      data: {
        event: 'action_stream',
        data: { action: 'created' },
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.event).toBe('action_stream');
    expect(body.data.fired).toBe(true);
  });

  test('POST /broadcast/test rejects invalid event type', async () => {
    const res = await api.post('/api/v1/broadcast/test', {
      headers: authHeaders(),
      data: { event: 'invalid_event' },
    });
    expect(res.status()).toBe(422);
  });

  test('broadcast auth works for private user channel', async () => {
    const res = await api.post('/broadcasting/auth', {
      headers: authHeaders(),
      data: {
        socket_id: '123456.654321',
        channel_name: 'private-user.1',
      },
    });
    // Should succeed for user's own channel (admin user is id=1)
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body).toHaveProperty('auth');
  });

  test('broadcast auth works for crm.contacts channel', async () => {
    const res = await api.post('/broadcasting/auth', {
      headers: authHeaders(),
      data: {
        socket_id: '123456.654321',
        channel_name: 'private-crm.contacts',
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body).toHaveProperty('auth');
  });

  test('broadcast auth works for crm.leads channel', async () => {
    const res = await api.post('/broadcasting/auth', {
      headers: authHeaders(),
      data: {
        socket_id: '123456.654321',
        channel_name: 'private-crm.leads',
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body).toHaveProperty('auth');
  });

  test('broadcast auth works for presence team channel', async () => {
    const res = await api.post('/broadcasting/auth', {
      headers: authHeaders(),
      data: {
        socket_id: '123456.654321',
        channel_name: 'presence-crm.team',
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body).toHaveProperty('auth');
    expect(body).toHaveProperty('channel_data');
  });
});
