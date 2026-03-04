import { test, expect, APIRequestContext } from '@playwright/test';

const BASE_URL = 'http://localhost:8190';
const API = `${BASE_URL}/api/v1`;
const TS = Date.now();

test.describe('Public REST API', () => {
  test.describe('Authentication', () => {
    test('can login and get token', async ({ request }) => {
      const response = await request.post(`${API}/auth/login`, {
        data: {
          email: 'admin@example.com',
          password: 'admin123',
        },
      });
      expect(response.ok()).toBeTruthy();
      const json = await response.json();
      expect(json.token).toBeTruthy();
      expect(json.token_type).toBe('Bearer');
      expect(json.user.email).toBe('admin@example.com');
    });

    test('rejects invalid credentials', async ({ request }) => {
      const response = await request.post(`${API}/auth/login`, {
        data: {
          email: 'admin@example.com',
          password: 'wrongpassword',
        },
      });
      expect(response.status()).toBe(401);
    });

    test('unauthenticated request returns 401', async ({ request }) => {
      const response = await request.get(`${API}/contacts`, {
        headers: { 'Accept': 'application/json' },
      });
      expect(response.status()).toBe(401);
    });
  });

  test.describe('Authenticated API', () => {
    let ctx: APIRequestContext;

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
    });

    test.afterAll(async () => {
      await ctx?.dispose();
    });

    test('GET /auth/me returns current user', async () => {
      const response = await ctx.get(`${API}/auth/me`);
      expect(response.ok()).toBeTruthy();
      const json = await response.json();
      expect(json.data.email).toBe('admin@example.com');
    });

    // --- Contacts ---
    test('GET /contacts returns paginated list', async () => {
      const response = await ctx.get(`${API}/contacts`);
      expect(response.ok()).toBeTruthy();
      const json = await response.json();
      expect(json.data).toBeInstanceOf(Array);
      expect(json).toHaveProperty('current_page');
    });

    test('POST /contacts creates a contact', async () => {
      const response = await ctx.post(`${API}/contacts`, {
        data: {
          name: 'API Test Contact',
          emails: [{ value: `apitest-${TS}@example.com`, label: 'work' }],
          contact_numbers: [{ value: '555-0100', label: 'work' }],
        },
      });
      expect(response.status()).toBe(201);
      const json = await response.json();
      expect(json.data.name).toBe('API Test Contact');
      expect(json.data.id).toBeTruthy();
    });

    test('GET /contacts/:id returns a single contact', async () => {
      const createResp = await ctx.post(`${API}/contacts`, {
        data: {
          name: 'Fetch Test Contact',
          emails: [{ value: `fetch-${TS}@example.com`, label: 'work' }],
        },
      });
      const created = await createResp.json();

      const response = await ctx.get(`${API}/contacts/${created.data.id}`);
      expect(response.ok()).toBeTruthy();
      const json = await response.json();
      expect(json.data.name).toBe('Fetch Test Contact');
    });

    test('PUT /contacts/:id updates a contact', async () => {
      const createResp = await ctx.post(`${API}/contacts`, {
        data: {
          name: 'Update Me',
          emails: [{ value: `updateme-${TS}@example.com`, label: 'work' }],
        },
      });
      const created = await createResp.json();

      const response = await ctx.put(`${API}/contacts/${created.data.id}`, {
        data: { name: 'Updated Name' },
      });
      expect(response.ok()).toBeTruthy();
      const json = await response.json();
      expect(json.data.name).toBe('Updated Name');
    });

    test('DELETE /contacts/:id deletes a contact', async () => {
      const createResp = await ctx.post(`${API}/contacts`, {
        data: {
          name: 'Delete Me',
          emails: [{ value: `deleteme-${TS}@example.com`, label: 'work' }],
        },
      });
      const created = await createResp.json();

      const response = await ctx.delete(`${API}/contacts/${created.data.id}`);
      expect(response.ok()).toBeTruthy();

      const getResp = await ctx.get(`${API}/contacts/${created.data.id}`);
      expect(getResp.ok()).toBeFalsy();
    });

    test('GET /contacts supports search', async () => {
      const uniqueName = `SearchUniq${TS}`;
      await ctx.post(`${API}/contacts`, {
        data: {
          name: uniqueName,
          emails: [{ value: `search-${TS}@example.com`, label: 'work' }],
        },
      });
      const response = await ctx.get(`${API}/contacts?search=${uniqueName}`);
      expect(response.ok()).toBeTruthy();
      const json = await response.json();
      expect(json.data.length).toBeGreaterThan(0);
    });

    // --- Pipelines ---
    test('GET /pipelines returns all pipelines', async () => {
      const response = await ctx.get(`${API}/pipelines`);
      expect(response.ok()).toBeTruthy();
      const json = await response.json();
      expect(json.data).toBeInstanceOf(Array);
      expect(json.data.length).toBeGreaterThan(0);
    });

    test('GET /pipelines/:id returns pipeline with stages', async () => {
      const listResp = await ctx.get(`${API}/pipelines`);
      const pipelines = await listResp.json();
      const pipelineId = pipelines.data[0].id;

      const response = await ctx.get(`${API}/pipelines/${pipelineId}`);
      expect(response.ok()).toBeTruthy();
      const json = await response.json();
      expect(json.data.stages).toBeTruthy();
    });

    // --- Leads ---
    test('POST /leads creates a lead', async () => {
      const pipelineResp = await ctx.get(`${API}/pipelines`);
      const pipelines = await pipelineResp.json();
      const pipeline = pipelines.data[0];

      const stageResp = await ctx.get(`${API}/pipelines/${pipeline.id}`);
      const pipelineDetail = await stageResp.json();
      const stageId = pipelineDetail.data.stages[0].id;

      const response = await ctx.post(`${API}/leads`, {
        data: {
          title: `API Test Lead ${TS}`,
          lead_value: 5000,
          lead_pipeline_id: pipeline.id,
          lead_pipeline_stage_id: stageId,
        },
      });
      expect(response.status()).toBe(201);
      const json = await response.json();
      expect(json.data.title).toContain('API Test Lead');
    });

    test('GET /leads returns paginated list', async () => {
      const response = await ctx.get(`${API}/leads`);
      expect(response.ok()).toBeTruthy();
      const json = await response.json();
      expect(json.data).toBeInstanceOf(Array);
    });

    test('GET /leads supports pipeline_id filter', async () => {
      const pipelineResp = await ctx.get(`${API}/pipelines`);
      const pipelines = await pipelineResp.json();
      const pipelineId = pipelines.data[0].id;

      const response = await ctx.get(`${API}/leads?pipeline_id=${pipelineId}`);
      expect(response.ok()).toBeTruthy();
    });

    // --- Activities ---
    test('POST /activities creates an activity', async () => {
      const response = await ctx.post(`${API}/activities`, {
        data: {
          title: `API Test Call ${TS}`,
          type: 'call',
          comment: 'Test activity from API',
        },
      });
      expect(response.status()).toBe(201);
      const json = await response.json();
      expect(json.data.title).toContain('API Test Call');
    });

    test('GET /activities returns list', async () => {
      const response = await ctx.get(`${API}/activities`);
      expect(response.ok()).toBeTruthy();
      const json = await response.json();
      expect(json.data).toBeInstanceOf(Array);
    });

    test('GET /activities supports type filter', async () => {
      const response = await ctx.get(`${API}/activities?type=call`);
      expect(response.ok()).toBeTruthy();
    });

    // --- Tags ---
    test('POST /tags creates a tag', async () => {
      const response = await ctx.post(`${API}/tags`, {
        data: {
          name: `api-test-tag-${TS}`,
          color: '#FF0000',
        },
      });
      expect(response.status()).toBe(201);
      const json = await response.json();
      expect(json.data.name).toContain('api-test-tag');
    });

    test('GET /tags returns all tags', async () => {
      const response = await ctx.get(`${API}/tags`);
      expect(response.ok()).toBeTruthy();
      const json = await response.json();
      expect(json.data).toBeInstanceOf(Array);
    });

    // --- Logout ---
    test('POST /auth/logout invalidates token', async ({ playwright }) => {
      // Create a separate token
      const tmpCtx = await playwright.request.newContext();
      const loginResp = await tmpCtx.post(`${API}/auth/login`, {
        data: { email: 'admin@example.com', password: 'admin123' },
      });
      const { token: tmpToken } = await loginResp.json();
      await tmpCtx.dispose();

      const authCtx = await playwright.request.newContext({
        extraHTTPHeaders: {
          'Authorization': `Bearer ${tmpToken}`,
          'Accept': 'application/json',
        },
      });

      const logoutResp = await authCtx.post(`${API}/auth/logout`);
      expect(logoutResp.ok()).toBeTruthy();

      // Verify token no longer works
      const meResp = await authCtx.get(`${API}/auth/me`);
      expect(meResp.status()).toBe(401);
      await authCtx.dispose();
    });
  });
});
