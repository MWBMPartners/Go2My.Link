# G2My.Link — Default Shortlink Domain (Component B)

The redirect engine that resolves short codes to their destination URLs.

- **Domain:** [g2my.link](https://g2my.link)
- **Purpose:** Short URL resolution and redirection
- **Part of:** [GoToMyLink](https://github.com/MWBMPartners/GoToMyLink) by MWBM Partners Ltd

## Directory Structure

```
G2My.Link/
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

- Short code resolution with 302 redirect
- Alias chain resolution (max 3 hops)
- Date-range validity enforcement
- Destination URL validation
- Custom organisation domain support
- Activity logging for analytics
- Error/fallback pages (404, expired, validation failure)
