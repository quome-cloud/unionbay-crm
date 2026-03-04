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

test.describe('Activity Reports - Summary', () => {
  test('GET /reports/activities/summary returns activity stats', async () => {
    const res = await api.get('/api/v1/reports/activities/summary', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('total');
    expect(body.data).toHaveProperty('completed');
    expect(body.data).toHaveProperty('completion_rate');
    expect(body.data).toHaveProperty('by_type');
    expect(body.data).toHaveProperty('date_range');
    expect(typeof body.data.total).toBe('number');
    expect(typeof body.data.completion_rate).toBe('number');
    expect(body.data.total).toBeGreaterThanOrEqual(0);
    expect(body.data.completion_rate).toBeGreaterThanOrEqual(0);
    expect(body.data.completion_rate).toBeLessThanOrEqual(100);
  });

  test('supports date range filter', async () => {
    const res = await api.get('/api/v1/reports/activities/summary?date_from=2026-01-01&date_to=2026-12-31', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.date_range.from).toBe('2026-01-01');
    expect(body.data.date_range.to).toBe('2026-12-31');
  });

  test('supports user_id filter', async () => {
    const res = await api.get(`/api/v1/reports/activities/summary?user_id=${userId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('total');
  });
});

test.describe('Activity Reports - By User', () => {
  test('GET /reports/activities/by-user returns per-user breakdown', async () => {
    const res = await api.get('/api/v1/reports/activities/by-user', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('users');
    expect(body.data.users).toBeInstanceOf(Array);
    expect(body.data).toHaveProperty('date_range');

    if (body.data.users.length > 0) {
      const user = body.data.users[0];
      expect(user).toHaveProperty('user_id');
      expect(user).toHaveProperty('user_name');
      expect(user).toHaveProperty('total');
      expect(user).toHaveProperty('completed');
      expect(user).toHaveProperty('completion_rate');
    }
  });

  test('supports date range filter', async () => {
    const res = await api.get('/api/v1/reports/activities/by-user?date_from=2026-01-01&date_to=2026-12-31', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.date_range.from).toBe('2026-01-01');
  });
});

test.describe('Activity Reports - Leaderboard', () => {
  test('GET /reports/activities/leaderboard returns ranked users', async () => {
    const res = await api.get('/api/v1/reports/activities/leaderboard', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('leaderboard');
    expect(body.data.leaderboard).toBeInstanceOf(Array);
    expect(body.data).toHaveProperty('metric');
    expect(body.data.metric).toBe('completed');

    if (body.data.leaderboard.length > 0) {
      expect(body.data.leaderboard[0]).toHaveProperty('rank');
      expect(body.data.leaderboard[0].rank).toBe(1);
    }
  });

  test('supports metric parameter', async () => {
    const res = await api.get('/api/v1/reports/activities/leaderboard?metric=total', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.metric).toBe('total');

    // Verify descending order
    const lb = body.data.leaderboard;
    for (let i = 1; i < lb.length; i++) {
      expect(lb[i - 1].total).toBeGreaterThanOrEqual(lb[i].total);
    }
  });

  test('supports completion_rate metric', async () => {
    const res = await api.get('/api/v1/reports/activities/leaderboard?metric=completion_rate', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.metric).toBe('completion_rate');
  });
});

test.describe('Activity Reports - Trends', () => {
  test('GET /reports/activities/trends returns daily trend data', async () => {
    const res = await api.get('/api/v1/reports/activities/trends', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('trends');
    expect(body.data.trends).toBeInstanceOf(Array);
    expect(body.data).toHaveProperty('interval');
    expect(body.data.interval).toBe('day');

    if (body.data.trends.length > 0) {
      expect(body.data.trends[0]).toHaveProperty('period');
      expect(body.data.trends[0]).toHaveProperty('total');
      expect(body.data.trends[0]).toHaveProperty('completed');
    }
  });

  test('supports weekly interval', async () => {
    const res = await api.get('/api/v1/reports/activities/trends?interval=week&date_from=2026-01-01&date_to=2026-12-31', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.interval).toBe('week');
  });

  test('supports monthly interval', async () => {
    const res = await api.get('/api/v1/reports/activities/trends?interval=month&date_from=2026-01-01&date_to=2026-12-31', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.interval).toBe('month');
  });

  test('supports user_id filter', async () => {
    const res = await api.get(`/api/v1/reports/activities/trends?user_id=${userId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('trends');
  });
});
