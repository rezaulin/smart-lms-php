# Smart LMS - PHP Version

Conversion dari Golang + PostgreSQL ke PHP + MySQL.

## Tech Stack
- PHP 8.1+
- MySQL 8.0+
- Pure PHP (no framework)
- REST API
- JWT Authentication

## Structure
```
smart-lms-php/
├── app/
│   ├── Controllers/    # HTTP request handlers
│   ├── Models/         # Database models
│   ├── Middleware/     # Auth, CORS, Rate limiting
│   ├── Utils/          # Helpers, validators
│   └── Config/         # Database, JWT config
├── public/             # Web root
│   ├── index.php       # Entry point
│   ├── assets/         # CSS, JS, images
│   └── uploads/        # User uploads
├── storage/            # Logs, cache, temp files
├── database/           # Migrations, seeds
└── views/              # HTML templates (optional)
```

## Features
- ✅ Online attendance (QR + GPS geofencing)
- ✅ Online exams (timer, anti-cheat, auto-grading)
- ✅ Online billing/SPP (invoices, discounts)
- ✅ Report cards (PDF generation)
- ✅ WhatsApp gateway (auto-notify parents)
- ✅ PWA support

## Installation
```bash
# 1. Clone & install dependencies
composer install

# 2. Setup database
mysql -u root -p < database/schema.sql

# 3. Configure .env
cp .env.example .env

# 4. Run migrations
php database/migrate.php

# 5. Seed demo data
php database/seed.php

# 6. Start server
php -S localhost:8085 -t public/
```

## API Endpoints
See `docs/API.md` for full documentation.

## Original Project
Converted from: http://185.245.61.91:3000/
Original stack: Golang (Fiber) + PostgreSQL
