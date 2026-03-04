import { test, expect, APIRequestContext } from '@playwright/test';

const BASE_URL = 'http://localhost:8190';
const API = `${BASE_URL}/api/v1`;
const TS = Date.now();

test.describe('Action Stream API', () => {
  let ctx: APIRequestContext;
  let contactId: number;

  test.beforeAll(async ({ playwright }) => {
    const tmpCtx = await playwright.request.newContext();
    const loginResp = await tmpCtx.post(`${API}/auth/login`, {
      data: { email: 'admin@example.com', password: 'admin123' },
    });
    const { token } = await loginResp.json();
    await tmpCtx.dispose();

    ctx = await playwright.request.newContext({
      extraHTTPHeaders: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    });

    // Create a test contact for linking actions
    const contactResp = await ctx.post(`${API}/contacts`, {
      data: {
        name: `ActionStream Test ${TS}`,
        emails: [{ value: `as-test-${TS}@example.com`, label: 'work' }],
      },
    });
    const contactJson = await contactResp.json();
    contactId = contactJson.data.id;
  });

  test.afterAll(async () => {
    await ctx?.dispose();
  });

  test('POST /action-stream creates a next action', async () => {
    const response = await ctx.post(`${API}/action-stream`, {
      data: {
        actionable_type: 'persons',
        actionable_id: contactId,
        action_type: 'call',
        description: `Follow up call ${TS}`,
        due_date: '2026-03-10',
        priority: 'high',
      },
    });
    expect(response.status()).toBe(201);
    const json = await response.json();
    expect(json.data.action_type).toBe('call');
    expect(json.data.description).toContain('Follow up call');
    expect(json.data.priority).toBe('high');
    expect(json.data.status).toBe('pending');
  });

  test('GET /action-stream returns prioritized list', async () => {
    const response = await ctx.get(`${API}/action-stream`);
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data).toBeInstanceOf(Array);
    expect(json.data.length).toBeGreaterThan(0);
  });

  test('GET /action-stream/:id returns single action', async () => {
    const createResp = await ctx.post(`${API}/action-stream`, {
      data: {
        actionable_type: 'persons',
        actionable_id: contactId,
        action_type: 'email',
        description: `Show test ${TS}`,
        priority: 'normal',
      },
    });
    const created = await createResp.json();

    const response = await ctx.get(`${API}/action-stream/${created.data.id}`);
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data.description).toContain('Show test');
  });

  test('PUT /action-stream/:id updates an action', async () => {
    const createResp = await ctx.post(`${API}/action-stream`, {
      data: {
        actionable_type: 'persons',
        actionable_id: contactId,
        action_type: 'task',
        description: `Update test ${TS}`,
        priority: 'low',
      },
    });
    const created = await createResp.json();

    const response = await ctx.put(`${API}/action-stream/${created.data.id}`, {
      data: { priority: 'urgent', description: 'Updated description' },
    });
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data.priority).toBe('urgent');
  });

  test('POST /action-stream/:id/complete marks action as completed', async () => {
    const createResp = await ctx.post(`${API}/action-stream`, {
      data: {
        actionable_type: 'persons',
        actionable_id: contactId,
        action_type: 'call',
        description: `Complete test ${TS}`,
      },
    });
    const created = await createResp.json();

    const response = await ctx.post(`${API}/action-stream/${created.data.id}/complete`);
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data.status).toBe('completed');
    expect(json.data.completed_at).toBeTruthy();
  });

  test('POST /action-stream/:id/snooze snoozes an action', async () => {
    const createResp = await ctx.post(`${API}/action-stream`, {
      data: {
        actionable_type: 'persons',
        actionable_id: contactId,
        action_type: 'meeting',
        description: `Snooze test ${TS}`,
      },
    });
    const created = await createResp.json();

    const response = await ctx.post(`${API}/action-stream/${created.data.id}/snooze`, {
      data: { snoozed_until: '2026-03-15 09:00:00' },
    });
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data.status).toBe('snoozed');
    expect(json.data.snoozed_until).toBeTruthy();
  });

  test('DELETE /action-stream/:id deletes an action', async () => {
    const createResp = await ctx.post(`${API}/action-stream`, {
      data: {
        actionable_type: 'persons',
        actionable_id: contactId,
        action_type: 'task',
        description: `Delete test ${TS}`,
      },
    });
    const created = await createResp.json();

    const response = await ctx.delete(`${API}/action-stream/${created.data.id}`);
    expect(response.ok()).toBeTruthy();

    const getResp = await ctx.get(`${API}/action-stream/${created.data.id}`);
    expect(getResp.ok()).toBeFalsy();
  });

  test('GET /action-stream supports action_type filter', async () => {
    const response = await ctx.get(`${API}/action-stream?action_type=call`);
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    for (const item of json.data) {
      expect(item.action_type).toBe('call');
    }
  });

  test('GET /action-stream supports priority filter', async () => {
    const response = await ctx.get(`${API}/action-stream?priority=high`);
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    for (const item of json.data) {
      expect(item.priority).toBe('high');
    }
  });

  test('GET /action-stream/overdue-count returns count', async () => {
    const response = await ctx.get(`${API}/action-stream/overdue-count`);
    expect(response.ok()).toBeTruthy();
    const json = await response.json();
    expect(json.data).toHaveProperty('overdue_count');
    expect(typeof json.data.overdue_count).toBe('number');
  });

  test('completed actions do not appear in prioritized list', async () => {
    // Create and complete an action
    const createResp = await ctx.post(`${API}/action-stream`, {
      data: {
        actionable_type: 'persons',
        actionable_id: contactId,
        action_type: 'task',
        description: `Completed filter test ${TS}`,
      },
    });
    const created = await createResp.json();
    await ctx.post(`${API}/action-stream/${created.data.id}/complete`);

    // Verify it's not in the prioritized list
    const listResp = await ctx.get(`${API}/action-stream`);
    const json = await listResp.json();
    const found = json.data.find((a: any) => a.id === created.data.id);
    expect(found).toBeUndefined();
  });

  test('validates actionable_type is persons or leads', async () => {
    const response = await ctx.post(`${API}/action-stream`, {
      data: {
        actionable_type: 'invalid',
        actionable_id: 1,
        action_type: 'call',
      },
    });
    expect(response.status()).toBe(422);
  });

  test('validates priority values', async () => {
    const response = await ctx.post(`${API}/action-stream`, {
      data: {
        actionable_type: 'persons',
        actionable_id: contactId,
        action_type: 'call',
        priority: 'invalid_priority',
      },
    });
    expect(response.status()).toBe(422);
  });
});
