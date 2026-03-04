# Product Requirements Document: OnePageCRM Feature Specification

**Date:** 2026-03-04
**Purpose:** Comprehensive feature listing of OnePageCRM as a reference specification for CRM development.
**Source:** https://www.onepagecrm.com

---

## 1. Core Philosophy: Action Stream & Next Actions

OnePageCRM's defining concept is the "Action Stream" — a dynamic, color-coded to-do list that ensures every contact has a scheduled follow-up.

| Requirement ID | Feature | Description | Plan |
|---|---|---|---|
| AS-001 | Action Stream | Centralized workspace that prioritizes follow-ups as a to-do list | All |
| AS-002 | Next Actions | Assign upcoming tasks/reminders to every prospect, lead, or client | All |
| AS-003 | Color-Coded Priority | Visual urgency indicators on actions (overdue, due today, upcoming) | All |
| AS-004 | Team Stream | View team-wide actions and activity feed | All |
| AS-005 | Distraction-Free Workspace | Minimal, clean interface focused on executing rather than managing | All |

---

## 2. Contact Management

| Requirement ID | Feature | Description | Plan |
|---|---|---|---|
| CM-001 | Unlimited Contacts | No cap on number of contacts stored | All |
| CM-002 | 360-Degree Contact View | All info on one scrollable page (calls, notes, deals, reminders, emails) | All |
| CM-003 | Unlimited Custom Fields | User-defined fields on contact records | All |
| CM-004 | Unlimited Tags | Tag contacts for segmentation and filtering | All |
| CM-005 | Contact Notes | Organize and access client notes from contact records | All |
| CM-006 | File Attachments | Attach documents/files to contacts | All |
| CM-007 | Account Management | B2B: define relationships between contacts and companies | All |
| CM-008 | Contact Hierarchies | Link contacts to parent organizations | All |
| CM-009 | Contact & Deal Restore | 30-day restore capability for deleted contacts and deals | Business |
| CM-010 | Required Custom Fields | Enforce mandatory custom fields on contact creation | Business |
| CM-011 | Split View | View contact details alongside list views | All |
| CM-012 | Real-Time Sync | Cross-device synchronization of contact data | All |

---

## 3. Lead Generation & Capture

| Requirement ID | Feature | Description | Plan |
|---|---|---|---|
| LG-001 | Lead Clipper (Browser Extension) | Chrome/Edge extension to capture contact info from any web page or social profile in one click | All |
| LG-002 | Web Forms | Embeddable online forms synced with CRM | Pro (3) / Biz (unlimited) |
| LG-003 | Instant Social Lead Capture | Capture leads from LinkedIn, Twitter, and other social profiles via Lead Clipper | All |
| LG-004 | Mobile Lead Capture | Capture leads directly from mobile devices | All |
| LG-005 | AI Business Card Scanner | AI-powered scanning of physical business cards; auto-creates CRM contacts with follow-up reminders | Business (500/month) |

---

## 4. Deal & Pipeline Management

| Requirement ID | Feature | Description | Plan |
|---|---|---|---|
| DM-001 | Kanban Board | Drag-and-drop deals across customizable stages | All |
| DM-002 | Customizable Deal Stages | Mirror your actual sales process with custom stages | All |
| DM-003 | Sales Pipeline | Manage deals from initial contact to close | All |
| DM-004 | Delivery Pipeline | Light project management for won deals / post-sale work | Business |
| DM-005 | Multiple Pipelines | Up to 10 sales + delivery pipelines | Business |
| DM-006 | Forecast View | Team performance overview against targets; predicts revenue | All |
| DM-007 | Revenue Forecasting | Automatic sales revenue prediction per deal stage probability | All |
| DM-008 | Deal Velocity | Track time spent in each deal stage; identify bottlenecks | Business |
| DM-009 | Inactive Days Tracking | Flag stalled deals based on days without activity | All |
| DM-010 | Unlimited Deals | No cap on number of deals | All |
| DM-011 | Products & Services Catalog | Save items with prices/costs; attach to deals quickly | All |
| DM-012 | Quick Quotes | Auto-generate simple sales quotes from deal data; send in one click | All (free add-on) |

---

## 5. Email Management

| Requirement ID | Feature | Description | Plan |
|---|---|---|---|
| EM-001 | Full Email Sync (Two-Way) | Send and receive emails from within CRM | All |
| EM-002 | Smart Email Filtering | Automatically stores only emails with CRM contacts; filters non-sales email | All |
| EM-003 | Shared Inbox | Centralized team email workspace | All |
| EM-004 | Unlimited Email Templates | Pre-built reusable message formats | All |
| EM-005 | Bulk Email Send | Send personalized emails in bulk (up to 450/day on Business) | All |
| EM-006 | Email Scheduling | Schedule messages for optimal delivery times | Business |
| EM-007 | Email Tracking & Notifications | Monitor email opens in real time | Business |
| EM-008 | Email History Fetching | Import historical emails into CRM | Business |
| EM-009 | Email Sequences | Trigger automated email series for outreach | Coming Soon |

---

## 6. Calls & Meetings

| Requirement ID | Feature | Description | Plan |
|---|---|---|---|
| CA-001 | Call Logging | Record call details within contact records | All |
| CA-002 | Speed Dial | Quick-call contacts directly from CRM | All |
| CA-003 | Click to Call | One-click calling integration | All |
| CA-004 | Meeting Notes | Log and organize meeting notes on contact profiles | All |
| CA-005 | Calendar Sync | Sync with Google Calendar, Outlook Calendar, or Apple Calendar | All |

---

## 7. Automation & Workflows

| Requirement ID | Feature | Description | Plan |
|---|---|---|---|
| AW-001 | Autoflow Workflows | Automate repetitive tasks without manual intervention | Pro (limited) / Biz (unlimited) |
| AW-002 | Pre-Saved Action Sequences | Reusable task sequences that can be triggered | All |
| AW-003 | Custom Autoflows | Build custom workflow automations with triggers and actions | Business |
| AW-004 | Unlimited Automations | No cap on number of workflow automations | Business |
| AW-005 | Contact Enrichment | Enrich contact data in one click via automated lookup | All |
| AW-006 | Web Form Automation | Form submissions auto-create CRM contacts with predefined Next Actions | All |

---

## 8. Reporting & Analytics

| Requirement ID | Feature | Description | Plan |
|---|---|---|---|
| RP-001 | Dynamic Dashboard | Real-time sales metrics with interactive charts and graphs | All |
| RP-002 | Dashboard Segmentation | Break down metrics by team, individual, filters, or date ranges | All |
| RP-003 | Custom Reports | Generate tailored reports on sales team activity and performance | All |
| RP-004 | Save & Schedule Reports | Save reports to run again; email on a set frequency | All |
| RP-005 | Report Export/Download | Download generated reports in various formats | All |
| RP-006 | Revenue Forecasting | Predict revenue from pending deals based on stage probability | All |
| RP-007 | Custom Activity Reports | Track and report on team activities (calls, meetings, emails) | All |

---

## 9. Team Collaboration

| Requirement ID | Feature | Description | Plan |
|---|---|---|---|
| TC-001 | Contact & Deal Assignment | Assign/reassign contacts and deals to team members | All |
| TC-002 | Comments & Mentions | Leave comments on contacts/deals; @mention colleagues for notifications | All |
| TC-003 | Notifications | Receive alerts about important changes to contacts, deals, or actions | All |
| TC-004 | User Roles & Permissions | Control what team members can view and edit | All |
| TC-005 | Advanced User Permissions | Granular per-user permission controls | Business |
| TC-006 | Focused User Role | Limited permission users who only see their own data (e.g., contractors) | Business |
| TC-007 | Team Stream | View team-wide actions and activity | All |

---

## 10. Mobile CRM

| Requirement ID | Feature | Description | Plan |
|---|---|---|---|
| MB-001 | Native iOS App | Full CRM on Apple App Store | All |
| MB-002 | Native Android App | Full CRM on Google Play Store | All |
| MB-003 | Mobile Action Stream | Full Action Stream access on mobile | All |
| MB-004 | Mobile Contact Management | View and manage contacts on the go | All |
| MB-005 | Mobile Deal Management | Progress deals through pipeline from mobile | All |
| MB-006 | Mobile Activity Logging | Log calls, meetings, and activities from mobile | All |
| MB-007 | Real-Time Sync | Bidirectional sync with web version | All |
| MB-008 | AI Route Planner | AI-powered sales route optimizer with Google Maps integration; optimizes customer visit routes with real-time traffic | All (free) |
| MB-009 | AI Business Card Scanner | Scan and digitize business cards on mobile | Business |

---

## 11. Security & Data

| Requirement ID | Feature | Description | Plan |
|---|---|---|---|
| SD-001 | 256-Bit Encryption | Banking-level encrypted connections | All |
| SD-002 | Daily Data Backups | Automated daily backup of all CRM data | All |
| SD-003 | Secure Server Hosting | Enterprise-grade hosting infrastructure | All |
| SD-004 | GDPR Compliance | Full GDPR compliance (Ireland-based company) | All |
| SD-005 | Role-Based Access Control | Permission system controlling data access by role | All |
| SD-006 | 30-Day Data Restore | Restore deleted contacts and deals within 30 days | Business |

---

## 12. Integrations

### 12.1 Native Integrations

| Category | Integrations |
|---|---|
| Email & Calendar | Google (Gmail, Contacts, Calendar, Drive), Outlook, Apple Calendar |
| Accounting & Finance | QuickBooks, Xero, FreshBooks, Sage Accounting |
| Marketing & Email | Mailchimp, Mailchimp Forms, Constant Contact, ActiveCampaign |
| Customer Service | Help Scout |
| Telephony / VoIP | Aircall, Talkdesk, Click to Call |
| Messaging | WhatsApp |
| Landing Pages / Forms | Unbounce, Wufoo |
| Productivity | Evernote, Dropbox, Google Drive |
| Automation Platforms | Zapier (5,000+ apps), Make.com |
| Communication | Skype |
| Documents | PandaDoc |
| Developer | REST API, Webhooks |

### 12.2 Built-in Free Add-ons

| Add-on | Description |
|---|---|
| Lead Clipper | Browser extension for one-click lead capture |
| Web Forms | Embeddable forms for website lead capture |
| Quick Quotes | Auto-generate and send sales quotes |
| Business Card Scanner | AI-powered card digitization |
| AI Route Planner | Sales route optimization with Google Maps |
| Sales Dialer | Built-in dialer for outbound calls |

---

## 13. Pricing

| Feature | Professional ($9.95/user/mo annual) | Business ($19.95/user/mo annual) |
|---|---|---|
| Contacts | Unlimited | Unlimited |
| Deals | Unlimited | Unlimited |
| Custom Fields | Unlimited | Unlimited |
| Tags | Unlimited | Unlimited |
| Action Stream | Yes | Yes |
| Web Forms | 3 | Unlimited |
| Email Templates | Unlimited | Unlimited |
| Email Sync | Full (send/receive) | Full (send/receive) |
| Bulk Email | Yes | Yes (450/day) |
| Email Tracking | No | Yes |
| Email Scheduling | No | Yes |
| Email History Fetching | No | Yes |
| Pipeline | Single | Multiple (up to 10) |
| Delivery Pipeline | No | Yes |
| Deal Velocity | No | Yes |
| Automations | Limited | Unlimited |
| AI Business Card Scanner | No | 500 scans/month |
| Advanced Permissions | No | Yes |
| Required Custom Fields | No | Yes |
| 30-Day Restore | No | Yes |
| Mobile Apps | Yes | Yes |
| AI Route Planner | Yes | Yes |
| Integrations | Yes | Yes |
| Reporting & Dashboard | Yes | Yes |

---

## 14. Key Differentiators

1. **Action-first philosophy** — the Action Stream forces a "next action" mindset on every contact
2. **Simplicity by design** — set up in 4 minutes; avoids feature bloat
3. **One-click lead capture** — Lead Clipper grabs contacts from any webpage
4. **360-degree scrollable contact page** — everything about a contact on one page
5. **Built-in AI tools at no extra cost** — Route Planner and Business Card Scanner
6. **Delivery Pipeline** — light project management for post-sale work
7. **Extremely affordable** — starting at $9.95/user/month
8. **No hidden fees or usage caps** — unlimited contacts, deals, fields, and tags on all plans
9. **Field sales focus** — AI Route Planner with Google Maps for in-person sales
10. **Target audience** — purpose-built for solopreneurs, small businesses, and small sales teams
