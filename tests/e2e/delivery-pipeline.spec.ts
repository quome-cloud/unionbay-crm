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

test.describe('Delivery Pipeline', () => {
  let deliveryPipelineId: number;

  test('default pipeline has sales type', async () => {
    const res = await api.get('/api/v1/pipelines?type=sales', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.length).toBeGreaterThan(0);
    // All filtered results should be sales type
    for (const pipeline of body.data) {
      expect(pipeline.pipeline_type).toBe('sales');
    }
  });

  test('can filter pipelines by type', async () => {
    const salesRes = await api.get('/api/v1/pipelines?type=sales', {
      headers: authHeaders(),
    });
    expect(salesRes.ok()).toBeTruthy();
    const salesBody = await salesRes.json();
    expect(salesBody.data.length).toBeGreaterThan(0);

    const deliveryRes = await api.get('/api/v1/pipelines?type=delivery', {
      headers: authHeaders(),
    });
    expect(deliveryRes.ok()).toBeTruthy();
    // delivery filter should only return delivery pipelines
    const deliveryBody = await deliveryRes.json();
    for (const pipeline of deliveryBody.data) {
      expect(pipeline.pipeline_type).toBe('delivery');
    }
  });

  test('can create a delivery pipeline', async () => {
    const res = await api.post('/api/v1/pipelines', {
      headers: authHeaders(),
      data: {
        name: `Delivery Pipeline ${TS}`,
        pipeline_type: 'delivery',
        rotten_days: 14,
        stages: [
          { name: 'Onboarding', code: 'onboarding', probability: 25 },
          { name: 'In Progress', code: 'in_progress', probability: 50 },
          { name: 'Review', code: 'review', probability: 75 },
          { name: 'Completed', code: 'completed', probability: 100 },
        ],
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.name).toBe(`Delivery Pipeline ${TS}`);
    expect(body.data.pipeline_type).toBe('delivery');
    expect(body.data.stages.length).toBe(4);
    deliveryPipelineId = body.data.id;
  });

  test('delivery pipeline appears in type=delivery filter', async () => {
    const res = await api.get('/api/v1/pipelines?type=delivery', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.length).toBeGreaterThanOrEqual(1);
    const found = body.data.find((p: any) => p.name === `Delivery Pipeline ${TS}`);
    expect(found).toBeTruthy();
  });

  test('delivery pipeline has correct stages', async () => {
    const res = await api.get(`/api/v1/pipelines/${deliveryPipelineId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    expect(body.data.stages.length).toBe(4);
    const codes = body.data.stages.map((s: any) => s.code);
    expect(codes).toContain('onboarding');
    expect(codes).toContain('in_progress');
    expect(codes).toContain('review');
    expect(codes).toContain('completed');
  });

  test('can create a sales pipeline via API', async () => {
    const res = await api.post('/api/v1/pipelines', {
      headers: authHeaders(),
      data: {
        name: `Sales Pipeline ${TS}`,
        pipeline_type: 'sales',
        stages: [
          { name: 'New', code: 'new', probability: 10 },
          { name: 'Qualified', code: 'qualified', probability: 50 },
          { name: 'Won', code: 'won', probability: 100 },
          { name: 'Lost', code: 'lost', probability: 0 },
        ],
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.pipeline_type).toBe('sales');
  });

  test('pipeline type defaults to sales', async () => {
    const res = await api.post('/api/v1/pipelines', {
      headers: authHeaders(),
      data: {
        name: `Default Type Pipeline ${TS}`,
        stages: [
          { name: 'Stage 1', code: 'stage_1', probability: 50 },
        ],
      },
    });
    expect(res.status()).toBe(201);
    const body = await res.json();
    expect(body.data.pipeline_type).toBe('sales');
  });

  test('rejects invalid pipeline_type', async () => {
    const res = await api.post('/api/v1/pipelines', {
      headers: { ...authHeaders(), Accept: 'application/json' },
      data: {
        name: `Invalid Type ${TS}`,
        pipeline_type: 'invalid',
        stages: [
          { name: 'Stage 1', code: 'stage_1', probability: 50 },
        ],
      },
    });
    expect(res.status()).toBe(422);
  });

  test('requires at least one stage', async () => {
    const res = await api.post('/api/v1/pipelines', {
      headers: { ...authHeaders(), Accept: 'application/json' },
      data: {
        name: `No Stages ${TS}`,
        stages: [],
      },
    });
    expect(res.status()).toBe(422);
  });
});

test.describe('Insurance Pipeline Stages', () => {
  let defaultPipelineId: number;

  test('find default pipeline', async () => {
    const res = await api.get('/api/v1/pipelines', {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    const defaultPipeline = body.data.find((p: any) => p.is_default === 1);
    expect(defaultPipeline).toBeTruthy();
    defaultPipelineId = defaultPipeline.id;
  });

  test('default pipeline has 8 insurance stages in correct order', async () => {
    const res = await api.get(`/api/v1/pipelines/${defaultPipelineId}`, {
      headers: authHeaders(),
    });
    expect(res.ok()).toBeTruthy();
    const body = await res.json();
    const stages = body.data.stages;
    expect(stages.length).toBeGreaterThanOrEqual(8);

    const expectedCodes = ['new', 'recruits', 'prospect', 'data-gathering', 'quoting', 'presenting', 'won', 'lost'];
    const expectedNames = ['New', 'Recruits', 'Prospect', 'Data Gathering', 'Quoting', 'Presenting', 'Won', 'Loss'];

    const sorted = [...stages].sort((a: any, b: any) => a.sort_order - b.sort_order);

    for (let i = 0; i < expectedCodes.length; i++) {
      expect(sorted[i].code).toBe(expectedCodes[i]);
      expect(sorted[i].name).toBe(expectedNames[i]);
    }
  });

  test('probabilities increase through the pipeline', async () => {
    const res = await api.get(`/api/v1/pipelines/${defaultPipelineId}`, {
      headers: authHeaders(),
    });
    const body = await res.json();
    const sorted = [...body.data.stages].sort((a: any, b: any) => a.sort_order - b.sort_order);

    const won = sorted.find((s: any) => s.code === 'won');
    const lost = sorted.find((s: any) => s.code === 'lost');
    expect(won.probability).toBe(100);
    expect(lost.probability).toBe(0);

    const middle = sorted.filter((s: any) => !['won', 'lost'].includes(s.code));
    for (let i = 1; i < middle.length; i++) {
      expect(middle[i].probability).toBeGreaterThanOrEqual(middle[i - 1].probability);
    }
  });
});
