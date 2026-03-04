import { test, expect, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;
let userId: number;

test.beforeAll(async ({ playwright }) => {
  api = await playwright.request.newContext({ baseURL: BASE });

  const login = await api.post('/api/v1/auth/login', {
    data: { email: 'admin@example.com', password: 'admin123' },
  });
  expect(login.ok()).toBeTruthy();
  const body = await login.json();
  token = body.token || body.data?.token;

  const me = await api.get('/api/v1/auth/me', {
    headers: { Authorization: `Bearer ${token}` },
  });
  userId = (await me.json()).data.id;
});

test.afterAll(async () => {
  await api.dispose();
});

function authHeaders() {
  return { Authorization: `Bearer ${token}` };
}

test.describe('Dashboard API', () => {
  test('GET /dashboard returns all dashboard sections', async () => {
    const res = await api.get('/api/v1/dashboard', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('leads');
    expect(body.data).toHaveProperty('activities');
    expect(body.data).toHaveProperty('revenue');
    expect(body.data).toHaveProperty('pipeline');
    expect(body.data).toHaveProperty('date_range');
  });

  test('leads stats have correct shape', async () => {
    const res = await api.get('/api/v1/dashboard', {
      headers: authHeaders(),
    });
    const body = await res.json();
    const leads = body.data.leads;
    expect(leads).toHaveProperty('total');
    expect(leads).toHaveProperty('created');
    expect(leads).toHaveProperty('active');
    expect(leads).toHaveProperty('won');
    expect(leads).toHaveProperty('lost');
    expect(typeof leads.total).toBe('number');
  });

  test('activities stats have correct shape', async () => {
    const res = await api.get('/api/v1/dashboard', {
      headers: authHeaders(),
    });
    const body = await res.json();
    const activities = body.data.activities;
    expect(activities).toHaveProperty('total');
    expect(activities).toHaveProperty('completed');
    expect(activities).toHaveProperty('overdue');
    expect(activities).toHaveProperty('by_type');
  });

  test('revenue stats have correct shape', async () => {
    const res = await api.get('/api/v1/dashboard', {
      headers: authHeaders(),
    });
    const body = await res.json();
    const revenue = body.data.revenue;
    expect(revenue).toHaveProperty('won_this_period');
    expect(revenue).toHaveProperty('won_all_time');
    expect(revenue).toHaveProperty('pipeline_value');
  });

  test('supports user_id filter', async () => {
    const res = await api.get(`/api/v1/dashboard?user_id=${userId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.leads).toHaveProperty('total');
  });

  test('supports date range filter', async () => {
    const res = await api.get('/api/v1/dashboard?date_from=2026-01-01&date_to=2026-12-31', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.date_range.from).toBe('2026-01-01');
    expect(body.data.date_range.to).toBe('2026-12-31');
  });

  test('supports combined user and date filters', async () => {
    const res = await api.get(`/api/v1/dashboard?user_id=${userId}&date_from=2026-01-01&date_to=2026-12-31`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('leads');
    expect(body.data).toHaveProperty('revenue');
  });

  test('pipeline stats is an array of stage objects', async () => {
    const res = await api.get('/api/v1/dashboard', {
      headers: authHeaders(),
    });
    const body = await res.json();
    expect(body.data.pipeline).toBeInstanceOf(Array);
    if (body.data.pipeline.length > 0) {
      expect(body.data.pipeline[0]).toHaveProperty('stage_name');
      expect(body.data.pipeline[0]).toHaveProperty('deal_count');
      expect(body.data.pipeline[0]).toHaveProperty('total_value');
    }
  });

  test('all numeric values are non-negative', async () => {
    const res = await api.get('/api/v1/dashboard', {
      headers: authHeaders(),
    });
    const body = await res.json();
    expect(body.data.leads.total).toBeGreaterThanOrEqual(0);
    expect(body.data.activities.total).toBeGreaterThanOrEqual(0);
    expect(body.data.revenue.won_this_period).toBeGreaterThanOrEqual(0);
    expect(body.data.revenue.pipeline_value).toBeGreaterThanOrEqual(0);
  });
});
