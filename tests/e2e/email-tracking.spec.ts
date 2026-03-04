import { test, expect, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;
let testEmailId: number;
let trackingId: string;

test.beforeAll(async ({ playwright }) => {
  api = await playwright.request.newContext({ baseURL: BASE });

  const login = await api.post('/api/v1/auth/login', {
    data: { email: 'admin@example.com', password: 'admin123' },
  });
  expect(login.ok()).toBeTruthy();
  const body = await login.json();
  token = body.token || body.data?.token;

  // Create a test email directly via DB (through the scheduled email which creates an email record)
  const emailRes = await api.post('/api/v1/scheduled-emails', {
    headers: { ...authHeaders(), Accept: 'application/json' },
    data: {
      subject: `Tracking Test ${Date.now()}`,
      reply: '<p>Test email for tracking</p>',
      to: [{ address: 'recipient@example.com', name: 'Recipient' }],
      scheduled_at: new Date(Date.now() + 48 * 60 * 60 * 1000).toISOString().replace('T', ' ').substring(0, 19),
    },
  });
  if (emailRes.status() !== 201) {
    console.log('Scheduled email creation failed:', emailRes.status(), await emailRes.text());
  }
  expect(emailRes.status()).toBe(201);
  const emailBody = await emailRes.json();
  testEmailId = emailBody.data.email_id;
});

test.afterAll(async () => {
  await api.dispose();
});

function authHeaders() {
  return { Authorization: `Bearer ${token}` };
}

test.describe('Email Tracking - Generate', () => {
  test('POST /emails/tracking/generate creates tracking ID and URLs', async () => {
    const res = await api.post('/api/v1/emails/tracking/generate', {
      headers: authHeaders(),
      data: { email_id: testEmailId },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('tracking_id');
    expect(body.data).toHaveProperty('pixel_url');
    expect(body.data).toHaveProperty('click_url');
    expect(body.data.pixel_url).toContain('/track/open/');
    expect(body.data.click_url).toContain('/track/click/');
    trackingId = body.data.tracking_id;
  });

  test('calling generate again returns same tracking ID', async () => {
    const res = await api.post('/api/v1/emails/tracking/generate', {
      headers: authHeaders(),
      data: { email_id: testEmailId },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.tracking_id).toBe(trackingId);
  });
});

test.describe('Email Tracking - Open Pixel', () => {
  test('GET /track/open/:trackingId returns transparent GIF', async () => {
    const res = await api.get(`/api/v1/track/open/${trackingId}`);
    expect(res.ok()).toBeTruthy();
    expect(res.headers()['content-type']).toBe('image/gif');
  });

  test('open event is recorded', async () => {
    // Hit the pixel a second time
    await api.get(`/api/v1/track/open/${trackingId}`);

    const res = await api.get(`/api/v1/emails/${testEmailId}/tracking`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.open_count).toBeGreaterThanOrEqual(2);
    expect(body.data).toHaveProperty('first_opened_at');
    expect(body.data).toHaveProperty('last_opened_at');
  });

  test('invalid tracking ID still returns pixel (no error)', async () => {
    const res = await api.get('/api/v1/track/open/nonexistent-id');
    expect(res.ok()).toBeTruthy();
    expect(res.headers()['content-type']).toBe('image/gif');
  });
});

test.describe('Email Tracking - Click Tracking', () => {
  test('GET /track/click/:trackingId redirects to URL', async () => {
    const targetUrl = 'https://example.com/test-page';
    const res = await api.get(
      `/api/v1/track/click/${trackingId}?url=${encodeURIComponent(targetUrl)}`,
      { maxRedirects: 0 }
    );
    // Should be a redirect (302)
    expect(res.status()).toBe(302);
  });

  test('click event is recorded', async () => {
    const res = await api.get(`/api/v1/emails/${testEmailId}/tracking`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.click_count).toBeGreaterThanOrEqual(1);

    const clickEvents = body.data.events.filter((e: any) => e.event_type === 'click');
    expect(clickEvents.length).toBeGreaterThanOrEqual(1);
    expect(clickEvents[0]).toHaveProperty('url_clicked');
  });

  test('click without URL returns 400', async () => {
    const res = await api.get(`/api/v1/track/click/${trackingId}`);
    expect(res.status()).toBe(400);
  });
});

test.describe('Email Tracking - Events & Summary', () => {
  test('GET /emails/:id/tracking returns full event list', async () => {
    const res = await api.get(`/api/v1/emails/${testEmailId}/tracking`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.email_id).toBe(testEmailId);
    expect(body.data.events).toBeInstanceOf(Array);
    expect(body.data.events.length).toBeGreaterThan(0);

    const event = body.data.events[0];
    expect(event).toHaveProperty('event_type');
    expect(event).toHaveProperty('ip_address');
    expect(event).toHaveProperty('created_at');
  });

  test('GET /emails/tracking/summary returns aggregated tracking data', async () => {
    const res = await api.get(`/api/v1/emails/tracking/summary?email_ids[]=${testEmailId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    if (body.data.length > 0) {
      const summary = body.data[0];
      expect(summary).toHaveProperty('email_id');
      expect(summary).toHaveProperty('open_count');
      expect(summary).toHaveProperty('click_count');
    }
  });
});
