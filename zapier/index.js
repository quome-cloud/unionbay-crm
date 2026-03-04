const authentication = require('./authentication');
const triggers = require('./triggers');
const actions = require('./actions');

module.exports = {
  version: require('./package.json').version,
  platformVersion: require('zapier-platform-core').version,

  authentication: authentication,

  triggers: {
    [triggers.newContact.key]: triggers.newContact,
    [triggers.newLead.key]: triggers.newLead,
    [triggers.leadStageChanged.key]: triggers.leadStageChanged,
    [triggers.dealWon.key]: triggers.dealWon,
    [triggers.dealLost.key]: triggers.dealLost,
    [triggers.newActivity.key]: triggers.newActivity,
    [triggers.emailReceived.key]: triggers.emailReceived,
  },

  creates: {
    [actions.createContact.key]: actions.createContact,
    [actions.createLead.key]: actions.createLead,
    [actions.updateLeadStage.key]: actions.updateLeadStage,
    [actions.createActivity.key]: actions.createActivity,
    [actions.sendEmail.key]: actions.sendEmail,
  },
};
