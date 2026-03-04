/**
 * Zapier triggers using webhook subscriptions.
 * Each trigger subscribes/unsubscribes to CRM webhook events.
 */

function makeWebhookTrigger(event, label, description, sampleData) {
  return {
    key: event,
    noun: label,
    display: {
      label: label,
      description: description,
    },
    operation: {
      type: 'hook',
      performSubscribe: {
        url: '{{bundle.authData.baseUrl}}/api/v1/webhooks/subscribe',
        method: 'POST',
        headers: {
          Authorization: 'Bearer {{bundle.authData.token}}',
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: {
          event: event,
          target_url: '{{bundle.targetUrl}}',
        },
      },
      performUnsubscribe: {
        url: '{{bundle.authData.baseUrl}}/api/v1/webhooks/{{bundle.subscribeData.id}}',
        method: 'DELETE',
        headers: {
          Authorization: 'Bearer {{bundle.authData.token}}',
          Accept: 'application/json',
        },
      },
      perform: (z, bundle) => {
        return [bundle.cleanedRequest];
      },
      performList: {
        url: '{{bundle.authData.baseUrl}}/api/v1/webhooks/events',
        method: 'GET',
        headers: {
          Authorization: 'Bearer {{bundle.authData.token}}',
          Accept: 'application/json',
        },
      },
      sample: sampleData,
    },
  };
}

module.exports = {
  newContact: makeWebhookTrigger(
    'new_contact',
    'New Contact',
    'Triggers when a new contact is created in the CRM.',
    { id: 1, name: 'John Doe', email: 'john@example.com', phone: '+1234567890', organization: 'Acme Inc' }
  ),

  newLead: makeWebhookTrigger(
    'new_lead',
    'New Lead',
    'Triggers when a new lead is created.',
    { id: 1, title: 'New Opportunity', status: 'new', lead_value: 10000, contact_name: 'Jane Smith' }
  ),

  leadStageChanged: makeWebhookTrigger(
    'lead_stage_changed',
    'Lead Stage Changed',
    'Triggers when a lead moves to a different pipeline stage.',
    { id: 1, title: 'Big Deal', old_stage: 'New', new_stage: 'Qualified', pipeline: 'Default' }
  ),

  dealWon: makeWebhookTrigger(
    'deal_won',
    'Deal Won',
    'Triggers when a deal is marked as won.',
    { id: 1, title: 'Enterprise Deal', value: 50000, contact_name: 'Bob Corp', closed_at: '2026-03-04' }
  ),

  dealLost: makeWebhookTrigger(
    'deal_lost',
    'Deal Lost',
    'Triggers when a deal is marked as lost.',
    { id: 1, title: 'Lost Opportunity', value: 20000, reason: 'Budget constraints', contact_name: 'Small Biz' }
  ),

  newActivity: makeWebhookTrigger(
    'new_activity',
    'New Activity',
    'Triggers when a new activity (call, meeting, note, task) is logged.',
    { id: 1, type: 'call', title: 'Follow-up call', contact_name: 'John Doe', created_at: '2026-03-04T10:00:00Z' }
  ),

  emailReceived: makeWebhookTrigger(
    'email_received',
    'Email Received',
    'Triggers when a new email is received and synced to the CRM.',
    { id: 1, subject: 'Re: Proposal', from: 'client@example.com', contact_name: 'Client Contact', received_at: '2026-03-04T10:00:00Z' }
  ),
};
