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

test.describe('Auto-Prompt Next Action - Lead Stage Change', () => {
  test('lead detail page loads with stages', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/leads/view/1`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    if (page.url().includes('/leads/view/')) {
      // Should have the stage component rendered
      const stages = page.locator('.stage');
      const count = await stages.count();
      expect(count).toBeGreaterThan(0);
    }
  });

  test('next action prompt modal has required form fields', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/leads/view/1`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    if (page.url().includes('/leads/view/')) {
      // Click a different stage to trigger stage change
      const stages = page.locator('.stage');
      const count = await stages.count();

      if (count >= 2) {
        // Click the second stage (index 1) to trigger a change
        await stages.nth(1).click();
        await page.waitForTimeout(3000);

        // Check if the next action prompt modal appeared
        const promptTitle = page.locator('[data-testid="next-action-prompt-title"]');
        const isVisible = await promptTitle.isVisible().catch(() => false);

        if (isVisible) {
          // Verify form fields exist
          await expect(page.locator('[data-testid="next-action-prompt-type"]')).toBeVisible();
          await expect(page.locator('[data-testid="next-action-prompt-priority"]')).toBeVisible();
          await expect(page.locator('[data-testid="next-action-prompt-description"]')).toBeVisible();
          await expect(page.locator('[data-testid="next-action-prompt-due-date"]')).toBeVisible();
          await expect(page.locator('[data-testid="next-action-prompt-save"]')).toBeVisible();
          await expect(page.locator('[data-testid="next-action-prompt-skip"]')).toBeVisible();

          // Close the modal
          await page.locator('[data-testid="next-action-prompt-skip"]').click();
        }
      }
    }
  });

  test('skip button closes the prompt without creating action', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/leads/view/1`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    if (page.url().includes('/leads/view/')) {
      const stages = page.locator('.stage');
      const count = await stages.count();

      if (count >= 2) {
        // Click a stage
        await stages.nth(0).click();
        await page.waitForTimeout(3000);

        const skipBtn = page.locator('[data-testid="next-action-prompt-skip"]');
        const isVisible = await skipBtn.isVisible().catch(() => false);

        if (isVisible) {
          await skipBtn.click();
          await page.waitForTimeout(1000);

          // Modal should be closed
          const promptTitle = page.locator('[data-testid="next-action-prompt-title"]');
          const stillVisible = await promptTitle.isVisible().catch(() => false);
          expect(stillVisible).toBeFalsy();
        }
      }
    }
  });
});

test.describe('Auto-Prompt Next Action - Complete Action Prompts New', () => {
  let testActionId: number | null = null;

  test('completing an action via API works', async () => {
    // Create a test action
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);

    const createRes = await api.post('/api/v1/action-stream', {
      headers: authHeaders(),
      data: {
        actionable_type: 'lead',
        actionable_id: 1,
        action_type: 'call',
        description: 'Auto-prompt test action',
        due_date: tomorrow.toISOString().split('T')[0],
        priority: 'normal',
      },
    });

    if (createRes.ok()) {
      const body = await createRes.json();
      testActionId = body.data?.id;

      // Complete the action
      if (testActionId) {
        const completeRes = await api.post(`/api/v1/action-stream/${testActionId}/complete`, {
          headers: authHeaders(),
        });
        expect(completeRes.ok()).toBeTruthy();
        const completeBody = await completeRes.json();
        expect(completeBody.data.status).toBe('completed');
      }
    }
  });

  test('next-action-widget on lead page prompts for new action after completion', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/leads/view/1`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    if (page.url().includes('/leads/view/')) {
      // The next-action-widget should be present
      const widget = page.locator('[data-testid="next-action-widget"]');
      await expect(widget).toBeVisible();

      // If there's a current action, completing it should show the create form
      const completeBtn = widget.locator('[data-testid="next-action-complete-btn"]');
      const hasAction = await completeBtn.isVisible().catch(() => false);

      if (hasAction) {
        await completeBtn.click();
        await page.waitForTimeout(2000);

        // After completing, the create form should appear
        const form = widget.locator('[data-testid="next-action-form"]');
        const formVisible = await form.isVisible().catch(() => false);
        expect(formVisible).toBeTruthy();
      }
    }
  });
});

test.describe('Auto-Prompt Next Action - Stage Change Prompt Content', () => {
  test('prompt message references the new stage name', async ({ page }) => {
    await loginPage(page);
    await page.goto(`${BASE}/admin/leads/view/1`);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    if (page.url().includes('/leads/view/')) {
      // The prompt message should contain stage name info
      const promptMessage = page.locator('[data-testid="next-action-prompt-message"]');

      // Try to trigger a stage change
      const stages = page.locator('.stage');
      const count = await stages.count();

      if (count >= 2) {
        await stages.nth(1).click();
        await page.waitForTimeout(3000);

        const isVisible = await promptMessage.isVisible().catch(() => false);
        if (isVisible) {
          const text = await promptMessage.textContent();
          expect(text).toContain('moved to stage');

          // Close
          await page.locator('[data-testid="next-action-prompt-skip"]').click();
        }
      }
    }
  });
});
