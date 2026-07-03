#!/bin/bash
# Database setup script

set -e

DB_NAME="${DB_NAME:-smart_lms}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_HOST="${DB_HOST:-localhost}"

echo "🗄️  Smart LMS Database Setup"
echo "================================"
echo ""

# Check if MySQL is available
if ! command -v mysql &> /dev/null; then
    echo "❌ MySQL client not found. Please install mysql-client."
    exit 1
fi

# Create database
echo "📦 Creating database: ${DB_NAME}..."
if [ -z "$DB_PASS" ]; then
    mysql -h"$DB_HOST" -u"$DB_USER" -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
else
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
fi

# Import schema
echo "🏗️  Importing schema..."
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ -z "$DB_PASS" ]; then
    mysql -h"$DB_HOST" -u"$DB_USER" "$DB_NAME" < "${SCRIPT_DIR}/schema.sql"
else
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "${SCRIPT_DIR}/schema.sql"
fi

echo ""
echo "✅ Database setup complete!"
echo ""
echo "Next steps:"
echo "1. Run: php database/seed.php (optional - for demo data)"
echo "2. Run: php -S localhost:8085 -t public/"
