#!/bin/bash
# Setup database on VPS
# Run this on VPS after deployment

set -e

DB_NAME="smart_lms"
DB_USER="smart_lms_user"
DB_PASS="SmartLMS2026!Secure"
SCHEMA_PATH="/var/www/smart-lms/schema.sql"

echo "═══════════════════════════════════════════════════"
echo "🗄️  SETTING UP DATABASE"
echo "═══════════════════════════════════════════════════"
echo ""

# Create database and user
echo "📝 Creating database and user..."
mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Import schema
echo "📦 Importing schema..."
mysql $DB_NAME < $SCHEMA_PATH

echo ""
echo "✅ DATABASE SETUP COMPLETE"
echo "📊 Database: $DB_NAME"
echo "👤 User: $DB_USER"
echo "🔒 Password: $DB_PASS"
echo ""
echo "⚠️  UPDATE .env WITH THESE CREDENTIALS"
