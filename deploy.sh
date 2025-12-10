#!/bin/bash
# Deployment Script for Attendance Management System
# Usage: ./deploy.sh

echo "=========================================="
echo "Attendance Management System Deployment"
echo "=========================================="
echo ""

# Server details
SERVER="169.239.251.102"
PORT="322"
USER="peter.mayen"
REMOTE_DIR="public_html"

echo "Step 1: Testing SSH connection..."
ssh -C $USER@$SERVER -p $PORT "echo 'SSH connection successful!'" || {
    echo "ERROR: Cannot connect to server. Please check:"
    echo "  - SSH credentials"
    echo "  - Server is accessible"
    echo "  - Port $PORT is open"
    exit 1
}

echo ""
echo "Step 2: Creating remote directory structure..."
ssh -C $USER@$SERVER -p $PORT "mkdir -p $REMOTE_DIR/assets/css $REMOTE_DIR/assets/js $REMOTE_DIR/includes"

echo ""
echo "Step 3: Uploading files..."
echo "Uploading PHP files..."
scp -P $PORT *.php $USER@$SERVER:~/$REMOTE_DIR/

echo "Uploading assets..."
scp -P $PORT -r assets/ $USER@$SERVER:~/$REMOTE_DIR/

echo "Uploading includes..."
scp -P $PORT -r includes/ $USER@$SERVER:~/$REMOTE_DIR/

echo "Uploading other files..."
scp -P $PORT .htaccess database_schema.sql $USER@$SERVER:~/$REMOTE_DIR/

echo ""
echo "Step 4: Setting file permissions..."
ssh -C $USER@$SERVER -p $PORT "cd $REMOTE_DIR && find . -type d -exec chmod 755 {} \; && find . -type f -exec chmod 644 {} \;"

echo ""
echo "Step 5: Deployment complete!"
echo ""
echo "Next steps:"
echo "1. SSH to server: ssh -C $USER@$SERVER -p $PORT"
echo "2. Update config.php with database credentials"
echo "3. Visit: http://yourdomain.com/init_db.php"
echo "4. Delete or protect init_db.php after setup"
echo ""
echo "=========================================="

