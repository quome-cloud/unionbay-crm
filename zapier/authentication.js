/**
 * Zapier authentication configuration.
 * Uses API token (Bearer) authentication via Quome CRM's login endpoint.
 */
module.exports = {
  type: 'custom',
  fields: [
    {
      key: 'baseUrl',
      label: 'CRM URL',
      type: 'string',
      required: true,
      helpText: 'Your Quome CRM URL (e.g., https://crm.yourcompany.com)',
    },
    {
      key: 'email',
      label: 'Email',
      type: 'string',
      required: true,
    },
    {
      key: 'password',
      label: 'Password',
      type: 'password',
      required: true,
    },
  ],
  test: {
    url: '{{bundle.authData.baseUrl}}/api/v1/contacts?limit=1',
    method: 'GET',
    headers: {
      Authorization: 'Bearer {{bundle.authData.token}}',
      Accept: 'application/json',
    },
  },
  connectionLabel: '{{bundle.authData.email}} @ {{bundle.authData.baseUrl}}',

  sessionConfig: {
    perform: {
      url: '{{bundle.authData.baseUrl}}/api/v1/auth/login',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: {
        email: '{{bundle.authData.email}}',
        password: '{{bundle.authData.password}}',
      },
    },
  },
};
