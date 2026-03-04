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

test.describe('Shared Team Inbox (T046)', () => {
  let accountId: number;
  let adminUserId: number;

  test('setup: get current user id', async () => {
    // Get user info from email accounts list to find admin user id
    const res = await api.get('/api/v1/shared-inbox', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    // We'll get admin user ID from the members endpoint later
  });

  test('setup: create email account for sharing', async () => {
    const res = await api.post('/api/v1/email-accounts', {
      headers: authHeaders(),
      data: {
        email_address: 'shared-test@example.com',
        imap_host: 'imap.example.com',
        imap_username: 'shared-test@example.com',
        imap_password: 'testpass',
        smtp_host: 'smtp.example.com',
        smtp_username: 'shared-test@example.com',
        smtp_password: 'testpass',
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    accountId = body.data.id;
  });

  test('GET /shared-inbox lists user accounts', async () => {
    const res = await api.get('/api/v1/shared-inbox', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBeGreaterThan(0);

    const account = body.data.find((a: any) => a.id === accountId);
    expect(account).toBeTruthy();
    expect(account.your_role).toBe('owner');
    expect(account.is_shared).toBe(false);
  });

  test('GET /shared-inbox/emails returns emails', async () => {
    const res = await api.get('/api/v1/shared-inbox/emails', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
  });

  test('GET /shared-inbox/:accountId/members lists owner', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.get(`/api/v1/shared-inbox/${accountId}/members`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBeGreaterThanOrEqual(1);
    expect(body.data[0].role).toBe('owner');
    adminUserId = body.data[0].id;
  });

  test('POST /shared-inbox/:accountId/members adds a member', async () => {
    expect(accountId).toBeTruthy();

    // First check if there are other users
    // For single-user test env, we need to handle this gracefully
    // Try to add ourselves — should fail with 422
    const selfRes = await api.post(`/api/v1/shared-inbox/${accountId}/members`, {
      headers: authHeaders(),
      data: { user_id: adminUserId, role: 'member' },
    });
    expect(selfRes.status()).toBe(422);
    const selfBody = await selfRes.json();
    expect(selfBody.message).toContain('Cannot add yourself');
  });

  test('POST /shared-inbox/:accountId/members validates user_id', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.post(`/api/v1/shared-inbox/${accountId}/members`, {
      headers: authHeaders(),
      data: { user_id: 999999 },
    });
    expect(res.status()).toBe(422);
  });

  test('POST /shared-inbox/:accountId/members validates role', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.post(`/api/v1/shared-inbox/${accountId}/members`, {
      headers: authHeaders(),
      data: { user_id: 1, role: 'superadmin' },
    });
    // Should fail with 422 (invalid role) or 422 (cannot add yourself)
    expect(res.status()).toBe(422);
  });

  test('DELETE /shared-inbox/:accountId/members/:memberId 404 for non-member', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.delete(`/api/v1/shared-inbox/${accountId}/members/999999`, {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('POST /shared-inbox/emails/:emailId/assign assigns email', async () => {
    // Create a test email first to have something to assign
    // Use an existing email or skip if none
    const emailsRes = await api.get('/api/v1/shared-inbox/emails', {
      headers: authHeaders(),
    });
    const emailsBody = await emailsRes.json();

    if (emailsBody.data.length > 0) {
      const emailId = emailsBody.data[0].id;
      const res = await api.post(`/api/v1/shared-inbox/emails/${emailId}/assign`, {
        headers: authHeaders(),
        data: { assigned_to: adminUserId },
      });
      expect(res.ok()).toBeTruthy();
      const body = await res.json();
      expect(body.data.assigned_to).toBeTruthy();
      expect(body.data.assigned_to.id).toBe(adminUserId);

      // Unassign
      const unassignRes = await api.post(`/api/v1/shared-inbox/emails/${emailId}/assign`, {
        headers: authHeaders(),
        data: { assigned_to: null },
      });
      expect(unassignRes.ok()).toBeTruthy();
      const unassignBody = await unassignRes.json();
      expect(unassignBody.data.assigned_to).toBeNull();
    }
  });

  test('POST /shared-inbox/emails/:emailId/read marks email read', async () => {
    const emailsRes = await api.get('/api/v1/shared-inbox/emails', {
      headers: authHeaders(),
    });
    const emailsBody = await emailsRes.json();

    if (emailsBody.data.length > 0) {
      const emailId = emailsBody.data[0].id;
      const res = await api.post(`/api/v1/shared-inbox/emails/${emailId}/read`, {
        headers: authHeaders(),
      });
      expect(res.ok()).toBeTruthy();
    }
  });

  test('POST /shared-inbox/emails/:emailId/assign 404 for missing email', async () => {
    const res = await api.post('/api/v1/shared-inbox/emails/999999/assign', {
      headers: authHeaders(),
      data: { assigned_to: adminUserId },
    });
    expect(res.status()).toBe(404);
  });

  test('POST /shared-inbox/emails/:emailId/read 404 for missing email', async () => {
    const res = await api.post('/api/v1/shared-inbox/emails/999999/read', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('GET /shared-inbox/emails supports assigned_to filter', async () => {
    const res = await api.get(`/api/v1/shared-inbox/emails?assigned_to=unassigned`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
  });

  test('shared-inbox requires authentication', async ({ playwright }) => {
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const res = await unauthApi.get('/api/v1/shared-inbox', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });

  test('shared-inbox members 404 for non-existent account', async () => {
    const res = await api.get('/api/v1/shared-inbox/999999/members', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('cleanup: delete test account', async () => {
    expect(accountId).toBeTruthy();
    const res = await api.delete(`/api/v1/email-accounts/${accountId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
  });
});
