# Deployment Guide

## Prerequisites

- Docker & Docker Compose
- Node.js 18+ (for E2E tests and Zapier CLI)
- Git

## Quick Start (Docker)

```bash
git clone <repo-url>
cd base_crm
docker compose up -d
```

This starts:
- **app** — Laravel PHP 8.2 application (Quome CRM)
- **nginx** — Reverse proxy on port 8190
- **db** — MySQL 8.0
- **redis** — Cache and queue backend
- **mailpit** — Local email testing (port 8025)
- **queue** — Laravel queue worker
- **scheduler** — Laravel task scheduler
- **soketi** — WebSocket server for real-time events

Access the CRM at: `http://localhost:8190/admin/login`

Default credentials:
- Email: `admin@example.com`
- Password: `admin123`

## Environment Configuration

Copy and customize the environment file:

```bash
cp crm/.env.example crm/.env
```

Key variables:

| Variable | Description | Default |
|----------|-------------|---------|
| `APP_URL` | Public URL of the CRM | `http://localhost` |
| `DB_HOST` | MySQL host | `db` (Docker service) |
| `DB_DATABASE` | Database name | `laravel-crm` |
| `DB_PASSWORD` | Database password | (set in docker-compose) |
| `REDIS_HOST` | Redis host | `redis` |
| `MAIL_HOST` | SMTP host | `mailpit` |
| `PUSHER_HOST` | WebSocket host | `soketi` |

## Database Migrations

Migrations run automatically on first start. To run manually:

```bash
docker compose exec app php artisan migrate
```

## White-Label Branding

Place brand assets in `crm/public/demo-brand/`:

- `logo.png` — Main logo (transparent PNG recommended)
- `logo-dark.png` — Logo for dark backgrounds
- `favicon.png` — Favicon (128x128)

Configure via API:
```bash
curl -X PUT http://localhost:8190/api/v1/white-label/config \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "company_name": "Your Company",
    "primary_color": "#2563eb",
    "accent_color": "#7c3aed"
  }'
```

## Email Account Setup

1. Navigate to **Email > Email Accounts** in the sidebar
2. Click **Add Email Account**
3. Enter IMAP/SMTP credentials
4. Enable email sync

API method:
```bash
curl -X POST http://localhost:8190/api/v1/email-accounts \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Work Email",
    "email": "user@company.com",
    "imap_host": "imap.gmail.com",
    "imap_port": 993,
    "imap_encryption": "ssl",
    "smtp_host": "smtp.gmail.com",
    "smtp_port": 587,
    "smtp_encryption": "tls",
    "password": "app-specific-password"
  }'
```

## Zapier Integration

The CRM includes a Zapier integration in the `zapier/` directory.

### Setup

```bash
cd zapier
npm install
zapier register  # First time only
zapier push
```

### Available Triggers
- New Contact Created
- New Lead Created
- Lead Stage Changed
- Deal Won / Deal Lost
- New Activity Created
- Email Received

### Available Actions
- Create Contact
- Create Lead
- Update Lead Stage
- Create Activity
- Send Email

## Make.com Integration

Configuration files are in `make-com/`. Import `app.json` into Make.com's custom app builder. Uses the same webhook subscription system as Zapier.

## API Authentication

```bash
# Get a bearer token
curl -X POST http://localhost:8190/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "password": "admin123"}'
```

Use the returned token in subsequent requests:
```
Authorization: Bearer <token>
```

## Running E2E Tests

```bash
npm install
npx playwright install
npx playwright test
```

Set `BASE_URL` to target a different environment:
```bash
BASE_URL=http://staging.example.com npx playwright test
```

## Railway Deployment (Union Bay CRM)

This project is deployed on [Railway](https://railway.app) with autodeploy from the `main` branch.

### Railway Project Details

| Setting | Value |
|---------|-------|
| **Project ID** | `d65119ac-4b43-483b-b54a-d911d465e464` |
| **Environment ID** | `07ef2598-e1e7-4486-8dc9-281112b70877` |
| **Service** | `crm-app` |
| **Repository** | `quome-cloud/unionbay-crm` |
| **Branch** | `main` (autodeploy) |
| **Dashboard** | https://railway.com/project/d65119ac-4b43-483b-b54a-d911d465e464 |

### How Autodeploy Works

Every push to `main` triggers a new Railway deployment automatically. The `crm-app` service is connected to the `quome-cloud/unionbay-crm` GitHub repo.

### Railway CLI

Install the Railway CLI for managing deployments from the terminal:

```bash
# Install
npm i -g @railway/cli
# or
brew install railway

# Login
railway login

# Link to this project
railway link d65119ac-4b43-483b-b54a-d911d465e464

# View logs
railway logs

# Open the Railway dashboard
railway open

# Deploy manually (bypasses autodeploy)
railway up

# Set environment variables
railway variables set KEY=VALUE

# Open a shell in the running service
railway shell
```

### Environment Variables on Railway

Configure these in the Railway dashboard or via CLI. Railway provides `DATABASE_URL`, `REDIS_URL`, etc. for linked services automatically.

Key variables to set:

| Variable | Description |
|----------|-------------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | Your Railway public domain |
| `APP_KEY` | Laravel app key (`php artisan key:generate --show`) |
| `SESSION_DRIVER` | `file` (recommended for Railway) |
| `QUEUE_CONNECTION` | `sync` (or `redis` if Redis service is added) |

### Viewing Deployment Status

```bash
# Via CLI
railway status

# Via dashboard
# https://railway.com/project/d65119ac-4b43-483b-b54a-d911d465e464/settings?environmentId=07ef2598-e1e7-4486-8dc9-281112b70877
```

## Production Considerations

1. **Change default credentials** — Update admin password immediately
2. **Set `APP_ENV=production`** and `APP_DEBUG=false`
3. **Configure real SMTP** — Replace mailpit with your SMTP provider
4. **Enable HTTPS** — Update nginx config with SSL certificates
5. **Set queue driver** — Use Redis for production: `QUEUE_CONNECTION=redis`
6. **Configure backups** — Use the built-in backup system (Settings > Automation > Data Transfer)
7. **Set up cron** — The scheduler container handles this in Docker; for bare-metal, add Laravel's scheduler to crontab
