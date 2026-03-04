/**
 * Zapier actions that create/modify CRM data.
 */

module.exports = {
  createContact: {
    key: 'create_contact',
    noun: 'Contact',
    display: {
      label: 'Create Contact',
      description: 'Creates a new contact in the CRM.',
    },
    operation: {
      inputFields: [
        { key: 'name', label: 'Name', type: 'string', required: true },
        { key: 'emails', label: 'Email', type: 'string', required: false, helpText: 'Contact email address' },
        { key: 'contact_numbers', label: 'Phone', type: 'string', required: false },
        { key: 'organization_id', label: 'Organization ID', type: 'integer', required: false },
      ],
      perform: {
        url: '{{bundle.authData.baseUrl}}/api/v1/contacts',
        method: 'POST',
        headers: {
          Authorization: 'Bearer {{bundle.authData.token}}',
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: {
          name: '{{bundle.inputData.name}}',
          emails: [{ value: '{{bundle.inputData.emails}}', label: 'work' }],
          contact_numbers: [{ value: '{{bundle.inputData.contact_numbers}}', label: 'work' }],
          organization_id: '{{bundle.inputData.organization_id}}',
        },
      },
      sample: {
        id: 1,
        name: 'John Doe',
        emails: [{ value: 'john@example.com', label: 'work' }],
      },
    },
  },

  createLead: {
    key: 'create_lead',
    noun: 'Lead',
    display: {
      label: 'Create Lead',
      description: 'Creates a new lead in the CRM.',
    },
    operation: {
      inputFields: [
        { key: 'title', label: 'Title', type: 'string', required: true },
        { key: 'lead_value', label: 'Value', type: 'number', required: false },
        { key: 'person_id', label: 'Contact ID', type: 'integer', required: false },
        { key: 'lead_pipeline_id', label: 'Pipeline ID', type: 'integer', required: false },
        { key: 'lead_pipeline_stage_id', label: 'Stage ID', type: 'integer', required: false },
      ],
      perform: {
        url: '{{bundle.authData.baseUrl}}/api/v1/leads',
        method: 'POST',
        headers: {
          Authorization: 'Bearer {{bundle.authData.token}}',
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: {
          title: '{{bundle.inputData.title}}',
          lead_value: '{{bundle.inputData.lead_value}}',
          person_id: '{{bundle.inputData.person_id}}',
          lead_pipeline_id: '{{bundle.inputData.lead_pipeline_id}}',
          lead_pipeline_stage_id: '{{bundle.inputData.lead_pipeline_stage_id}}',
        },
      },
      sample: {
        id: 1,
        title: 'New Opportunity',
        status: 1,
        lead_value: 10000,
      },
    },
  },

  updateLeadStage: {
    key: 'update_lead_stage',
    noun: 'Lead Stage',
    display: {
      label: 'Update Lead Stage',
      description: 'Moves a lead to a different pipeline stage.',
    },
    operation: {
      inputFields: [
        { key: 'lead_id', label: 'Lead ID', type: 'integer', required: true },
        { key: 'lead_pipeline_stage_id', label: 'New Stage ID', type: 'integer', required: true },
      ],
      perform: {
        url: '{{bundle.authData.baseUrl}}/api/v1/leads/{{bundle.inputData.lead_id}}',
        method: 'PUT',
        headers: {
          Authorization: 'Bearer {{bundle.authData.token}}',
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: {
          lead_pipeline_stage_id: '{{bundle.inputData.lead_pipeline_stage_id}}',
        },
      },
      sample: {
        id: 1,
        title: 'Updated Lead',
        lead_pipeline_stage_id: 2,
      },
    },
  },

  createActivity: {
    key: 'create_activity',
    noun: 'Activity',
    display: {
      label: 'Create Activity',
      description: 'Logs a new activity (call, meeting, note, task) in the CRM.',
    },
    operation: {
      inputFields: [
        { key: 'title', label: 'Title', type: 'string', required: true },
        { key: 'type', label: 'Type', type: 'string', required: true, choices: ['call', 'meeting', 'note', 'task', 'lunch', 'email'] },
        { key: 'comment', label: 'Description', type: 'text', required: false },
        { key: 'schedule_from', label: 'Start Date/Time', type: 'datetime', required: false },
        { key: 'schedule_to', label: 'End Date/Time', type: 'datetime', required: false },
        { key: 'participants', label: 'Contact IDs (comma-separated)', type: 'string', required: false },
      ],
      perform: {
        url: '{{bundle.authData.baseUrl}}/api/v1/activities',
        method: 'POST',
        headers: {
          Authorization: 'Bearer {{bundle.authData.token}}',
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: {
          title: '{{bundle.inputData.title}}',
          type: '{{bundle.inputData.type}}',
          comment: '{{bundle.inputData.comment}}',
          schedule_from: '{{bundle.inputData.schedule_from}}',
          schedule_to: '{{bundle.inputData.schedule_to}}',
        },
      },
      sample: {
        id: 1,
        title: 'Follow-up call',
        type: 'call',
        is_done: 0,
      },
    },
  },

  sendEmail: {
    key: 'send_email',
    noun: 'Email',
    display: {
      label: 'Send Email',
      description: 'Sends an email through the CRM.',
    },
    operation: {
      inputFields: [
        { key: 'to', label: 'To Email', type: 'string', required: true },
        { key: 'subject', label: 'Subject', type: 'string', required: true },
        { key: 'reply', label: 'Body (HTML)', type: 'text', required: true },
      ],
      perform: {
        url: '{{bundle.authData.baseUrl}}/api/v1/emails/bulk',
        method: 'POST',
        headers: {
          Authorization: 'Bearer {{bundle.authData.token}}',
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: {
          subject: '{{bundle.inputData.subject}}',
          body: '{{bundle.inputData.reply}}',
          recipients: ['{{bundle.inputData.to}}'],
        },
      },
      sample: {
        id: 1,
        subject: 'Hello from CRM',
        status: 'sent',
      },
    },
  },
};
