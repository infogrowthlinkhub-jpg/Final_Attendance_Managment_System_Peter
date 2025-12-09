# Quick Deployment Steps

## 1. Prepare Database on Live Server

**Via cPanel or phpMyAdmin:**
- Create database: `peter_mayen_attendance` (or your preferred name)
- Create database user
- Grant ALL PRIVILEGES to user on database
- **Note down:** Database name, username, password

## 2. Update config.php

Edit `config.php` with your live server database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_database_name');
```

## 3. Upload Files

**Via SFTP (FileZilla):**
- Host: `169.239.251.102`
- Port: `322`
- Username: `peter.mayen`
- Password: `73902026` (change on first login)
- Upload to: `public_html/` or `www/` directory

**Upload these:**
- All `.php` files
- `assets/` folder (with css and js subfolders)
- `includes/` folder
- `.htaccess`
- `attendance_schema.sql`

## 4. Set Permissions (via SSH)

```bash
ssh -C peter.mayen@169.239.251.102 -p 322
cd public_html
chmod 755 assets includes
chmod 644 *.php .htaccess
```

## 5. Initialize Database

Visit: `https://yourdomain.com/init_db.php`
- Wait for completion message
- Click "Go to Login Page"

## 6. Protect init_db.php

After database is initialized, either:
- Delete it: `rm init_db.php`
- Or protect it in `.htaccess` (uncomment the init_db.php protection)

## 7. Test

- Visit your domain
- Should redirect to login
- Create test account
- Test all features

## Done! 🎉

Your system is now live and ready to use!

