# Union Bay CRM - Project Instructions

## Customer
Union Bay Risk - paying customer deployed on Railway.

## Repository
- **Remote:** git@github.com:quome-cloud/unionbay-crm.git
- **Branch:** `main` (autodeploy to Railway)

## Trello Board (Project Management)
- **Board:** https://trello.com/b/IWLr4Pub/quome-union-bay-risk-todo
- **Board ID:** `69c4c35a9048e3ade832e623`
- **MCP Server:** Configured in `.mcp.json` (gitignored) using `@delorenj/mcp-server-trello`
- **Lists:** Backlog, In Progress, Review, Done
- **Labels:** Bug Fix (red), Feature (green), Infrastructure (blue), Urgent (orange)

### Trello Workflow
- When starting work on a task, move the card to **In Progress**
- If blocked, move to **Blocked** with a comment explaining why
- When submitting for review, move to **Review**
- When complete and committed, move to **Done**
- Add commit hashes to card descriptions when closing out work
- **When moving to Done:** Add verification notes and Playwright test screenshots/results as comments
- Use labels to categorize cards appropriately

## Railway Deployment
- **Project ID:** `d65119ac-4b43-483b-b54a-d911d465e464`
- **Environment ID:** `07ef2598-e1e7-4486-8dc9-281112b70877`
- **Service:** `crm-app`
- **Dashboard:** https://railway.com/project/d65119ac-4b43-483b-b54a-d911d465e464
- Pushes to `main` trigger autodeploy

## Testing Requirements
- **Write thorough Playwright E2E tests for every feature and fix** — this is a general requirement for most work
- Tests should validate primary user flows end-to-end
- Run `npx playwright test` to verify before committing
- Tests live alongside the project and run against the Docker Compose local environment

## Workflow
- Work one ticket at a time
- Commit each ticket separately with a descriptive message
- Move the Trello card through: Backlog → In Progress → Done
- Add commit hashes to card descriptions when closing out work

## Stack
- Laravel PHP 8.2 (Krayin CRM base)
- MySQL 8.0
- Redis (cache/queue)
- Docker Compose for local dev
- Playwright for E2E tests
