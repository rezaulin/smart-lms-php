# Smart LMS - Manual Deployment Guide
# VPS: 185.245.61.91

## Prerequisites on VPS
- Ubuntu/Debian with root access
- PHP 8.1+ with extensions (pdo, pdo_mysql, mbstring, json, curl)
- Nginx
- MySQL 8.0+
- Composer
- Git (optional)

## Step 1: Upload Files to VPS

### Option A: Using rsync (from local machine)
```bash
rsync -avz --delete \
  --exclude 'frontend/' \
  --exclude 'vendor/' \
  --exclude 'node_modules/' \
  --exclude '.git/' \
  --exclude '.env' \
  /root/smart-lms-php/ root@185.245.61.91:/var/www/smart-lms/
```

### Option B: Using Git (on VPS)
```bash
ssh root@185.245.61.91
cd /var/www
git clone https://github.com/rezaulin/smart-lms-php.git smart-lms
cd smart-lms
```

### Option C: Using scp (from local machine)
```bash
cd /root/smart-lms-php
tar -czf smart-lms.tar.gz --exclude='frontend' --exclude='vendor' --exclude='node_modules' --exclude='.git' .
scp smart-lms.tar.gz root@185.245.61.91:/tmp/
ssh root@185.245.61.91 "mkdir -p /var/www/smart-lms && cd /var/www/smart-lms && tar -xzf /tmp/smart-lms.tar.gz"
```

## Step 2: Setup Database (on VPS)

```bash
# Connect to VPS
ssh root@185.245.61.91

# Create database and user
mysql -e "CREATE DATABASE IF NOT EXISTS smart_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'smart_lms_user'@'localhost' IDENTIFIED BY 'SmartLMS2026!Secure';"
mysql -e "GRANT ALL PRIVILEGES ON smart_lms.* TO 'smart_lms_user'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Import schema
mysql smart_lms < /var/www/smart-lms/schema.sql

# Verify tables
mysql -e "USE smart_lms; SHOW TABLES;"
```

## Step 3: Configure Environment

```bash
# Create .env file
cd /var/www/smart-lms
cp .env.example .env

# Edit .env with actual values
nano .env
```

### Required .env values:
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=http://185.245.61.91

DB_HOST=localhost
DB_PORT=3306
DB_NAME=smart_lms
DB_USER=smart_lms_user
DB_PASS=SmartLMS2026!Secure

JWT_SECRET=your-super-secret-jwt-key-change-this-in-production-min-32-chars
JWT_EXPIRY=3600
JWT_REFRESH_EXPIRY=604800

TIMEZONE=Asia/Jakarta
```

## Step 4: Install Dependencies

```bash
cd /var/www/smart-lms

# Install composer dependencies
composer install --no-dev --optimize-autoloader

# Set permissions
chown -R www-data:www-data .
chmod -R 755 .
chmod -R 775 storage logs 2>/dev/null || true
```

## Step 5: Configure Nginx

```bash
# Copy nginx config
nano /etc/nginx/sites-available/smart-lms
```

Paste this config:
```nginx
server {
    listen 80;
    server_name 185.245.61.91;
    
    root /var/www/smart-lms/public;
    index index.php index.html;

    access_log /var/log/nginx/smart-lms-access.log;
    error_log /var/log/nginx/smart-lms-error.log;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_send_timeout 300;
    }

    location ~ /\. {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
    gzip_min_length 1000;
}
```

```bash
# Enable site
ln -s /etc/nginx/sites-available/smart-lms /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

## Step 6: Test Deployment

```bash
# Test PHP-FPM
systemctl status php8.1-fpm

# Test Nginx
systemctl status nginx

# Test database connection
mysql -u smart_lms_user -p smart_lms -e "SELECT COUNT(*) FROM schools;"

# Test API endpoint
curl http://185.245.61.91/api/auth/login
```

## Step 7: Create Admin User (SQL)

```bash
mysql smart_lms << EOF
INSERT INTO users (email, password, role, name, created_at) 
VALUES (
  'admin@smart-lms.local',
  '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- password: password
  'admin_pusat',
  'Super Admin',
  NOW()
);
EOF
```

## Troubleshooting

### Check logs
```bash
tail -f /var/log/nginx/smart-lms-error.log
tail -f /var/log/php8.1-fpm.log
```

### PHP version issues
```bash
update-alternatives --config php
```

### Permission issues
```bash
chown -R www-data:www-data /var/www/smart-lms
chmod -R 755 /var/www/smart-lms
```

### Database connection issues
```bash
mysql -u smart_lms_user -p
USE smart_lms;
SHOW TABLES;
```

## Next Steps
1. Test all API endpoints (see API-TESTING.md)
2. Deploy frontend build to VPS
3. Setup SSL with Let's Encrypt
4. Configure WhatsApp gateway
5. Setup monitoring & backups
