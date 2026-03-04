# Browser Extension (Lead Clipper) Planning Document

## Overview

Chrome/Edge browser extension (Manifest V3) that captures contact information from any web page or social media profile and creates a CRM contact in one click.

## Core Features

1. **One-click capture** — Click extension icon on any page to scrape visible contact info
2. **Social profile parsing** — Dedicated parsers for LinkedIn, Twitter/X, Facebook, Instagram
3. **Email signature parsing** — Extract contact details from email signatures in Gmail/Outlook web
4. **Quick form** — Popup form pre-filled with scraped data; user can edit before saving
5. **Next Action assignment** — Set a follow-up action immediately on capture
6. **Tag assignment** — Apply tags during capture
7. **Pipeline assignment** — Optionally create a deal during capture
8. **Auth** — Login to CRM instance via popup; store API token securely

## Technical Architecture

```
extension/
├── manifest.json          # Manifest V3 configuration
├── popup/                 # Extension popup (Vue.js mini-app)
│   ├── Popup.vue          # Main popup component
│   ├── LoginForm.vue      # CRM authentication
│   └── CaptureForm.vue    # Pre-filled contact form
├── content-scripts/       # Page scraping scripts
│   ├── generic.js         # Generic page scraper
│   ├── linkedin.js        # LinkedIn profile parser
│   ├── twitter.js         # Twitter/X profile parser
│   └── gmail.js           # Gmail email signature parser
├── background/            # Service worker
│   └── service-worker.js  # API calls, auth management
└── assets/                # Icons, styles
```

## API Dependencies

Requires the Public REST API with:
- Authentication endpoint
- Contact creation endpoint
- Tags list endpoint
- Pipeline/stage list endpoint
- Next Action creation endpoint

## Social Profile Parsing Rules

### LinkedIn
- Name from profile header
- Title/Position from headline
- Company from experience section
- Location from profile
- Profile URL

### Twitter/X
- Display name and handle
- Bio/description
- Website URL
- Location

### Generic Web Page
- Scan for email patterns (mailto: links, text matching)
- Scan for phone patterns
- Scan for structured data (schema.org, vCard)
- Scan for social links

## Testing

- **Unit tests:** Jest for parsing logic
- **Integration tests:** Mock Chrome APIs with jest-chrome
- **Manual testing:** Load unpacked extension in Chrome
- **Playwright E2E:** Test popup flows with Chrome extension loaded
