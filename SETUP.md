# Backend Setup Guide

## Prerequisites
- PHP 8.2+
- Composer
- MySQL 8.0+
- Node.js 18+ (for frontend)

## Steps

```bash
# 1. Install dependencies
composer install

# 2. Copy env
cp .env.example .env

# 3. Generate app key
php artisan key:generate

# 4. Configure .env
# Set DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 5. Run migrations
php artisan migrate

# 6. Seed master data
php artisan db:seed

# 7. Create storage link
php artisan storage:link

# 8. Start server
php artisan serve --port=8000
```

## Default Users (after seeding)
| Role | Email | Password |
|------|-------|----------|
| Administrator Sistem | admin@company.com | password123 |
| Project Manager | pm@company.com | password123 |
| Direksi | direksi@company.com | password123 |
| Finance | finance@company.com | password123 |
| Admin Proyek | adminproyek@company.com | password123 |
