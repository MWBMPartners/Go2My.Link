# Lnks.page — Links Page (Component C)

A LinkTree-like service for displaying curated lists of links on a single page.

- **Domain:** [lnks.page](https://lnks.page)
- **Purpose:** Customisable link listing pages for users and organisations
- **Part of:** [GoToMyLink](https://github.com/MWBMPartners/GoToMyLink) by MWBM Partners Ltd

## Directory Structure

```
Lnks.page/
├── _auth_keys/          ← Website-specific DB credentials (outside web root)
├── _includes/           ← Website-specific shared includes
├── _functions/          ← Website-specific shared functions
├── _libraries/          ← Website-specific libraries
├── _uploads/            ← Website-specific uploads (not web-accessible)
├── _backups/            ← Website-specific backups (not web-accessible)
├── private_html/        ← Private HTML (placeholder)
├── public_html/         ← Production web root
├── public_html_dev_alpha/  ← Alpha development web root
├── public_html_dev_beta/   ← Beta development web root
├── public_html_landing/    ← Pre-launch landing page
└── public_html_redir/      ← Redirection web root (limited use)
```

## Key Features

- Customisable link listing pages (lnks.page/{username})
- Multiple built-in templates (default, modern, minimal, bold, professional)
- WYSIWYG block-based template editor
- HTML template upload with placeholder markers
- Automatic favicon detection for destination sites
- Manual links (not from short URL service)
- Age verification gates for restricted content
- Custom domain fallback (org domain with no short code shows LinksPage)
