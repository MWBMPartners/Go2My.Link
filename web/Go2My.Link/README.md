# Go2My.Link — Main Website (Component A)

The public-facing website for the Go2My.Link URL shortening service.

- **Domain:** [go2my.link](https://go2my.link)
- **Admin:** [admin.go2my.link](https://admin.go2my.link)
- **Purpose:** Service promotion, user sign-up, short link creation, user/admin dashboard
- **Part of:** [Go2My.Link](https://github.com/MWBMPartners/Go2My.Link) by MWBM Partners Ltd

## Directory Structure

```
Go2My.Link/
├── _admin/              ← Admin/Dashboard application
│   └── public_html/     ← Admin web root (admin.go2my.link)
├── _auth_keys/          ← Website-specific DB credentials (outside web root)
├── _includes/           ← Website-specific shared includes
├── _functions/          ← Website-specific shared functions
├── _libraries/          ← Website-specific libraries
├── _uploads/            ← Website-specific uploads (not web-accessible)
├── _backups/            ← Website-specific backups (not web-accessible)
├── private_html/        ← Private HTML (placeholder)
├── public_html/         ← Production web root (go2my.link)
├── public_html_dev_alpha/  ← Alpha development web root
├── public_html_dev_beta/   ← Beta development web root
├── public_html_landing/    ← Pre-launch landing page
└── public_html_redir/      ← Redirection web root (limited use)
```

## Key Features

### Public Website (go2my.link)

- Homepage with URL shortening form
- User registration and authentication
- Static pages (about, features, pricing, legal)
- API endpoints

### Admin Dashboard (admin.go2my.link)

- User/organisation dashboard
- Short link management (create, edit, analytics)
- Organisation and domain management
- Settings and account management
- API key management
