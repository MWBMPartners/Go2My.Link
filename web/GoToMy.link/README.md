# GoToMy.link — Main Website (Component A)

The public-facing website for the GoToMyLink URL shortening service.

- **Domain:** [go2my.link](https://go2my.link)
- **Purpose:** Service promotion, user sign-up, short link creation, user/admin dashboard
- **Part of:** [GoToMyLink](https://github.com/MWBMPartners/GoToMyLink) by MWBM Partners Ltd

## Directory Structure

```
GoToMy.link/
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

- Homepage with URL shortening form
- User registration and authentication
- User/organisation dashboard
- Short link management (create, edit, analytics)
- API endpoints
- Static pages (about, features, pricing, legal)
