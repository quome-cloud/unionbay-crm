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

test.describe('Roles', () => {
  test('GET /roles lists all roles', async () => {
    const res = await api.get('/api/v1/roles', { headers: authHeaders() });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
    expect(body.data.length).toBeGreaterThanOrEqual(2);

    const roleNames = body.data.map((r: any) => r.name);
    expect(roleNames).toContain('Administrator');
    expect(roleNames).toContain('Focused User');
  });

  test('Focused User role has correct restricted permissions', async () => {
    const res = await api.get('/api/v1/roles', { headers: authHeaders() });
    const body = await res.json();
    const focusedRole = body.data.find((r: any) => r.name === 'Focused User');
    expect(focusedRole).toBeTruthy();
    expect(focusedRole.permission_type).toBe('custom');
    expect(focusedRole.permissions).toBeInstanceOf(Array);

    // Should have basic CRM permissions
    expect(focusedRole.permissions).toContain('dashboard');
    expect(focusedRole.permissions).toContain('leads');
    expect(focusedRole.permissions).toContain('contacts');
    expect(focusedRole.permissions).toContain('activities');

    // Should NOT have admin settings
    expect(focusedRole.permissions).not.toContain('settings');
    expect(focusedRole.permissions).not.toContain('settings.user');
    expect(focusedRole.permissions).not.toContain('settings.user.roles');
    expect(focusedRole.permissions).not.toContain('configuration');
  });

  test('Administrator role has full permissions', async () => {
    const res = await api.get('/api/v1/roles', { headers: authHeaders() });
    const body = await res.json();
    const adminRole = body.data.find((r: any) => r.name === 'Administrator');
    expect(adminRole).toBeTruthy();
    expect(adminRole.permission_type).toBe('all');
  });

  test('GET /roles/{id} shows a specific role', async () => {
    const res = await api.get('/api/v1/roles', { headers: authHeaders() });
    const body = await res.json();
    const focusedRole = body.data.find((r: any) => r.name === 'Focused User');

    const showRes = await api.get(`/api/v1/roles/${focusedRole.id}`, {
      headers: authHeaders(),
    });
    expect(showRes.ok()).toBeTruthy();
    const showBody = await showRes.json();
    expect(showBody.data.name).toBe('Focused User');
    expect(showBody.data).toHaveProperty('users_count');
  });

  test('GET /roles/{id} returns 404 for non-existent role', async () => {
    const res = await api.get('/api/v1/roles/999999', {
      headers: authHeaders(),
    });
    expect(res.status()).toBe(404);
  });

  test('Focused User role description explains restrictions', async () => {
    const res = await api.get('/api/v1/roles', { headers: authHeaders() });
    const body = await res.json();
    const focusedRole = body.data.find((r: any) => r.name === 'Focused User');
    expect(focusedRole.description).toContain('own contacts');
    expect(focusedRole.description).toContain('No access');
  });
});
