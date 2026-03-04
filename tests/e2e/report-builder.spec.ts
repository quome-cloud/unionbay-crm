import { test, expect, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';
const TS = Date.now();

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
  return { Authorization: `Bearer ${token}` };
}

test.describe('Report Builder - Schema', () => {
  test('GET /reports/schema returns entity types and columns', async () => {
    const res = await api.get('/api/v1/reports/schema', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('leads');
    expect(body.data).toHaveProperty('contacts');
    expect(body.data).toHaveProperty('activities');
    expect(body.data).toHaveProperty('products');

    // Check leads columns
    const leadsColumns = body.data.leads;
    expect(leadsColumns.length).toBeGreaterThan(0);
    const codes = leadsColumns.map((c: any) => c.code);
    expect(codes).toContain('id');
    expect(codes).toContain('title');
    expect(codes).toContain('lead_value');
    expect(codes).toContain('pipeline');
    expect(codes).toContain('stage');
  });
});

test.describe('Report Builder - Execute Ad-Hoc', () => {
  test('POST /reports/execute returns leads data', async () => {
    const res = await api.post('/api/v1/reports/execute', {
      headers: authHeaders(),
      data: {
        entity_type: 'leads',
        columns: ['id', 'title', 'lead_value'],
        sort_by: 'id',
        sort_order: 'desc',
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('rows');
    expect(body.data).toHaveProperty('total');
    expect(body.data.rows).toBeInstanceOf(Array);
  });

  test('POST /reports/execute with filters', async () => {
    const res = await api.post('/api/v1/reports/execute', {
      headers: authHeaders(),
      data: {
        entity_type: 'leads',
        columns: ['id', 'title', 'lead_value'],
        filters: [
          { column: 'lead_value', operator: '>', value: 0 },
        ],
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.rows).toBeInstanceOf(Array);
    // All rows should have lead_value > 0
    for (const row of body.data.rows) {
      expect(Number(row.lead_value)).toBeGreaterThan(0);
    }
  });

  test('POST /reports/execute with group_by', async () => {
    const res = await api.post('/api/v1/reports/execute', {
      headers: authHeaders(),
      data: {
        entity_type: 'leads',
        columns: ['stage', 'lead_value'],
        group_by: 'stage',
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.rows).toBeInstanceOf(Array);
    if (body.data.rows.length > 0) {
      expect(body.data.rows[0]).toHaveProperty('count');
    }
  });

  test('POST /reports/execute with join columns', async () => {
    const res = await api.post('/api/v1/reports/execute', {
      headers: authHeaders(),
      data: {
        entity_type: 'leads',
        columns: ['id', 'title', 'pipeline', 'stage', 'person'],
        sort_by: 'id',
        sort_order: 'desc',
        limit: 5,
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.rows).toBeInstanceOf(Array);
  });

  test('POST /reports/execute contacts entity type', async () => {
    const res = await api.post('/api/v1/reports/execute', {
      headers: authHeaders(),
      data: {
        entity_type: 'contacts',
        columns: ['id', 'name'],
        sort_by: 'id',
        sort_order: 'desc',
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.rows).toBeInstanceOf(Array);
  });

  test('POST /reports/execute activities entity type', async () => {
    const res = await api.post('/api/v1/reports/execute', {
      headers: authHeaders(),
      data: {
        entity_type: 'activities',
        columns: ['id', 'title', 'type'],
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.rows).toBeInstanceOf(Array);
  });

  test('rejects invalid entity_type', async () => {
    const res = await api.post('/api/v1/reports/execute', {
      headers: { ...authHeaders(), Accept: 'application/json' },
      data: {
        entity_type: 'invalid',
        columns: ['id'],
      },
    });
    expect(res.status()).toBe(422);
  });
});

test.describe('Report Builder - CRUD', () => {
  let reportId: number;

  test('POST /reports saves a report definition', async () => {
    const res = await api.post('/api/v1/reports', {
      headers: authHeaders(),
      data: {
        name: `Test Report ${TS}`,
        entity_type: 'leads',
        columns: ['id', 'title', 'lead_value', 'pipeline', 'stage'],
        filters: [{ column: 'lead_value', operator: '>', value: 0 }],
        sort_by: 'lead_value',
        sort_order: 'desc',
        chart_type: 'bar',
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.name).toBe(`Test Report ${TS}`);
    expect(body.data.entity_type).toBe('leads');
    expect(body.data.columns).toBeInstanceOf(Array);
    reportId = body.data.id;
  });

  test('GET /reports lists saved reports', async () => {
    const res = await api.get('/api/v1/reports', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    const found = body.data.find((r: any) => r.name === `Test Report ${TS}`);
    expect(found).toBeTruthy();
  });

  test('GET /reports/:id returns single report', async () => {
    const res = await api.get(`/api/v1/reports/${reportId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.id).toBe(reportId);
    expect(body.data.name).toBe(`Test Report ${TS}`);
  });

  test('PUT /reports/:id updates a report', async () => {
    const res = await api.put(`/api/v1/reports/${reportId}`, {
      headers: authHeaders(),
      data: {
        name: `Updated Report ${TS}`,
        chart_type: 'pie',
      },
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.name).toBe(`Updated Report ${TS}`);
    expect(body.data.chart_type).toBe('pie');
  });

  test('POST /reports/:id/execute runs saved report', async () => {
    const res = await api.post(`/api/v1/reports/${reportId}/execute`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('rows');
    expect(body.data).toHaveProperty('total');
  });

  test('DELETE /reports/:id deletes a report', async () => {
    const res = await api.delete(`/api/v1/reports/${reportId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();

    // Verify it's gone
    const check = await api.get(`/api/v1/reports/${reportId}`, {
      headers: { ...authHeaders(), Accept: 'application/json' },
    });
    expect(check.status()).toBe(404);
  });
});
