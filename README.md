# GrowthWorld Premium Tools (Root Deploy)

This repository now keeps the redesigned test deployment implementation at the **repository root** so Hostinger auto-deploy can map directly to this path.

## Root structure
- `index.php`, `api.php`, `sitemap.php`, `.htaccess`
- `config/` central config for DB/PayPal/admin/security values
- `core/` bootstrap + periodic subscription refresh script
- `templates/` page templates
- `static/` CSS/JS/images
- `magic_logs/` protected logs (`.htaccess` blocks direct access)
- `sql/schema.sql` complete `premium_` prefixed schema
- `growthworld-premium-tools-code/` legacy reference app

## Deployment note
Set document root to this repository root for test deployment.

## Cron
Run every 5 hours:

```bash
php core/subscription_refresh.php
```
