import { test, expect, Page, APIRequestContext } from '@playwright/test';

const BASE = process.env.BASE_URL || 'http://localhost:8190';

let api: APIRequestContext;
let token: string;

async function loginPage(page: Page) {
  await page.goto(`${BASE}/admin/login`);
  await page.waitForLoadState('networkidle');
  await page.fill('input[name="email"]', 'admin@example.com');
  await page.fill('input[name="password"]', 'admin123');
  await page.click('.primary-button');
  await page.waitForURL(/\/admin/, { timeout: 15000 });
}

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

test.describe('Notification Bell - UI', () => {
  test('bell icon is visible in header after login', async ({ page }) => {
    await loginPage(page);
    await page.waitForTimeout(1000);

    const bell = page.locator('[data-testid="notification-bell-btn"]');
    await expect(bell).toBeVisible();
  });

  test('clicking bell opens notification dropdown', async ({ page }) => {
    await loginPage(page);
    await page.waitForTimeout(1000);

    const bell = page.locator('[data-testid="notification-bell-btn"]');
    await bell.click();
    await page.waitForTimeout(1000);

    const dropdown = page.locator('[data-testid="notification-dropdown"]');
    await expect(dropdown).toBeVisible();

    // Should have "Notifications" heading
    await expect(dropdown.locator('h3:has-text("Notifications")')).toBeVisible();
  });

  test('notification dropdown shows list or empty state', async ({ page }) => {
    await loginPage(page);
    await page.waitForTimeout(1000);

    const bell = page.locator('[data-testid="notification-bell-btn"]');
    await bell.click();
    await page.waitForTimeout(2000);

    const list = page.locator('[data-testid="notification-list"]');
    await expect(list).toBeVisible();

    // Either has items or shows empty state
    const items = page.locator('[data-testid="notification-item"]');
    const empty = page.locator('[data-testid="notification-empty"]');

    const itemCount = await items.count();
    const emptyVisible = await empty.isVisible().catch(() => false);

    expect(itemCount > 0 || emptyVisible).toBeTruthy();
  });

  test('mark all read button appears when there are unread notifications', async ({ page }) => {
    await loginPage(page);
    await page.waitForTimeout(1000);

    const bell = page.locator('[data-testid="notification-bell-btn"]');
    await bell.click();
    await page.waitForTimeout(2000);

    const dropdown = page.locator('[data-testid="notification-dropdown"]');
    await expect(dropdown).toBeVisible();

    // Either mark-all-read button or empty state should be visible
    const markAllBtn = page.locator('[data-testid="notification-mark-all-read"]');
    const empty = page.locator('[data-testid="notification-empty"]');

    const hasMarkAll = await markAllBtn.isVisible().catch(() => false);
    const hasEmpty = await empty.isVisible().catch(() => false);

    // One of them should be present
    expect(hasMarkAll || hasEmpty).toBeTruthy();
  });
});

test.describe('Notification Bell - API', () => {
  test('GET /notifications returns paginated list', async () => {
    const res = await api.get('/api/v1/notifications', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toBeInstanceOf(Array);
  });

  test('GET /notifications/unread-count returns count', async () => {
    const res = await api.get('/api/v1/notifications/unread-count', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data).toHaveProperty('unread_count');
    expect(typeof body.data.unread_count).toBe('number');
  });

  test('PUT /notifications/read-all marks all as read', async () => {
    const res = await api.put('/api/v1/notifications/read-all', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();

    // Verify unread count is now 0
    const countRes = await api.get('/api/v1/notifications/unread-count', {
      headers: authHeaders(),
    });
    const body = await countRes.json();
    expect(body.data.unread_count).toBe(0);
  });

  test('notifications require authentication', async ({ playwright }) => {
    const unauthApi = await playwright.request.newContext({ baseURL: BASE });
    const res = await unauthApi.get('/api/v1/notifications', {
      headers: { Accept: 'application/json' },
    });
    expect(res.status()).toBe(401);
    await unauthApi.dispose();
  });
});
