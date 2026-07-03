#!/bin/bash
# Smart LMS - Deploy to VPS
# Target: 185.245.61.91

set -e

VPS_IP="185.245.61.91"
VPS_USER="root"
REMOTE_PATH="/var/www/smart-lms"
LOCAL_PATH="/root/smart-lms-php"

echo "═══════════════════════════════════════════════════"
echo "🚀 DEPLOYING SMART-LMS TO VPS"
echo "═══════════════════════════════════════════════════"
echo ""

# 1. Create remote directory
echo "📁 Creating remote directory..."
ssh $VPS_USER@$VPS_IP "mkdir -p $REMOTE_PATH"

# 2. Sync backend files (exclude frontend build, vendor, git)
echo "📤 Syncing backend files..."
rsync -avz --delete \
  --exclude 'frontend/' \
  --exclude 'vendor/' \
  --exclude 'node_modules/' \
  --exclude '.git/' \
  --exclude '.env' \
  $LOCAL_PATH/ $VPS_USER@$VPS_IP:$REMOTE_PATH/

# 3. Copy .env.example as .env (will edit manually)
echo "📝 Creating .env from example..."
ssh $VPS_USER@$VPS_IP "cd $REMOTE_PATH && cp .env.example .env"

# 4. Install composer dependencies
echo "📦 Installing composer dependencies..."
ssh $VPS_USER@$VPS_IP "cd $REMOTE_PATH && composer install --no-dev --optimize-autoloader"

# 5. Set permissions
echo "🔒 Setting permissions..."
ssh $VPS_USER@$VPS_IP "cd $REMOTE_PATH && chown -R www-data:www-data . && chmod -R 755 ."

echo ""
echo "✅ DEPLOYMENT COMPLETE"
echo "📍 Remote path: $REMOTE_PATH"
echo ""
echo "⏭️  NEXT STEPS:"
echo "1. Edit .env on VPS (database credentials)"
echo "2. Import schema.sql to MySQL"
echo "3. Setup Nginx configuration"
echo "4. Test API endpoints"
