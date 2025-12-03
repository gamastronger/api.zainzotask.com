# 🚀 Deployment Checklist - Zainzo Task App

## 📋 Domain Configuration

**Frontend:** `https://task.zainzo.com`  
**Backend:** `https://api.zainzo.com`

---

## ✅ Backend Laravel - Checklist Deployment

### 1. Environment Configuration (.env)

- [x] `APP_ENV=production`
- [x] `APP_DEBUG=false` (PENTING: harus false di production!)
- [x] `APP_URL=https://api.zainzo.com`
- [x] `FRONTEND_URL=https://task.zainzo.com`
- [x] `GOOGLE_REDIRECT_URI=https://api.zainzo.com/auth/google/callback`
- [x] `SANCTUM_STATEFUL_DOMAINS=task.zainzo.com`
- [x] `SESSION_DOMAIN=.zainzo.com`
- [ ] Update `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` sesuai production database
- [ ] Generate new `APP_KEY` untuk production: `php artisan key:generate`

### 2. Google OAuth Configuration

Pastikan di **Google Cloud Console** sudah dikonfigurasi:

**Authorized JavaScript origins:**

- `https://task.zainzo.com`
- `https://api.zainzo.com`

**Authorized redirect URIs:**

- `https://api.zainzo.com/auth/google/callback`

### 3. CORS Configuration

✅ File `config/cors.php` sudah diupdate:

```php
'allowed_origins' => [
    'https://task.zainzo.com',
    // 'http://localhost:5173', // Uncomment untuk local dev
],
```

### 4. Database Setup

```bash
# Di server production
php artisan migrate --force
php artisan db:seed  # Optional, jika ada seeder
```

### 5. Optimization Commands (WAJIB untuk Production)

```bash
# Clear all cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Cache untuk performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev
```

### 6. File Permissions (Linux/Unix)

```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 7. Web Server Configuration

#### Nginx Configuration Example

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.zainzo.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name api.zainzo.com;

    root /path/to/zainzotask-backend-laravel/public;
    index index.php index.html;

    # SSL certificates
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;

    # Add CORS headers
    add_header 'Access-Control-Allow-Origin' 'https://task.zainzo.com' always;
    add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS' always;
    add_header 'Access-Control-Allow-Headers' 'Authorization, Content-Type, X-Requested-With' always;
    add_header 'Access-Control-Allow-Credentials' 'true' always;

    # Handle preflight requests
    if ($request_method = 'OPTIONS') {
        return 204;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

#### Apache Configuration (.htaccess sudah ada)

Pastikan mod_rewrite enabled:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 8. SSL Certificate

```bash
# Menggunakan Let's Encrypt (Certbot)
sudo certbot --nginx -d api.zainzo.com
# atau
sudo certbot --apache -d api.zainzo.com
```

### 9. Environment Variables di Server

Jika menggunakan shared hosting atau cPanel, pastikan:

- PHP version minimal 8.1
- Enable extensions: mbstring, pdo_mysql, xml, curl, gd, zip
- Set `upload_max_filesize` dan `post_max_size` minimal 10M

### 10. Security Checklist

- [ ] APP_DEBUG=false (jangan sampai lupa!)
- [ ] Generate APP_KEY baru untuk production
- [ ] Set proper file permissions (755 untuk folder, 644 untuk file)
- [ ] Aktifkan HTTPS/SSL
- [ ] Disable directory listing
- [ ] Set secure headers (CSP, X-Frame-Options, dll)
- [ ] Rate limiting aktif (Laravel sudah default)
- [ ] Gunakan database user dengan privilege terbatas

---

## ✅ Frontend React - Checklist Deployment

### 1. Environment Configuration

Update file `.env` atau `.env.production`:

```env
VITE_API_URL=https://api.zainzo.com
VITE_FRONTEND_URL=https://task.zainzo.com
VITE_GOOGLE_CLIENT_ID=677524265473-8rr4si61qtkhlo6osb8288ie0006094n.apps.googleusercontent.com
```

### 2. Update API Base URL

Pastikan semua API calls menggunakan env variable:

```typescript
// services/api.ts atau axios config
const API_URL = import.meta.env.VITE_API_URL || 'https://api.zainzo.com';
axios.defaults.baseURL = `${API_URL}/api`;
```

### 3. Build untuk Production

```bash
npm run build
# atau
yarn build
```

### 4. Deploy ke Hosting

**Jika menggunakan Vercel/Netlify:**

```bash
# Vercel
vercel --prod

# Netlify
netlify deploy --prod
```

**Jika menggunakan cPanel/Shared Hosting:**

- Upload folder `dist/` ke public_html
- Buat `.htaccess` untuk SPA routing:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  RewriteRule ^index\.html$ - [L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule . /index.html [L]
</IfModule>
```

### 5. DNS Configuration

Set DNS records:

```
Type    Name    Value               TTL
A       task    [Your Server IP]    Auto
CNAME   api     [Backend Server]    Auto
```

---

## 🧪 Testing Checklist

### Backend Testing

```bash
# Test health check
curl https://api.zainzo.com/api/health-check

# Test CORS
curl -H "Origin: https://task.zainzo.com" \
     -H "Access-Control-Request-Method: POST" \
     -H "Access-Control-Request-Headers: Content-Type" \
     -X OPTIONS \
     https://api.zainzo.com/api/auth/google
```

### Frontend Testing

- [ ] Buka <https://task.zainzo.com>
- [ ] Test Google OAuth login
- [ ] Test create board
- [ ] Test create column
- [ ] Test create card
- [ ] Test drag & drop
- [ ] Test logout

### Browser Console Check

Pastikan tidak ada error:

- CORS error
- Mixed content error (http/https)
- 404 errors
- Authentication errors

---

## 🐛 Troubleshooting

### CORS Errors

1. Cek `config/cors.php` - pastikan domain sudah benar
2. Cek web server config (nginx/apache) - pastikan tidak ada conflict CORS headers
3. Clear cache: `php artisan config:clear && php artisan config:cache`

### 500 Internal Server Error

1. Cek `.env` - pastikan semua variable sudah benar
2. Cek file permissions: `chmod -R 755 storage bootstrap/cache`
3. Cek error logs: `tail -f storage/logs/laravel.log`
4. Pastikan `APP_DEBUG=false` di production

### Google OAuth Tidak Bekerja

1. Periksa Google Cloud Console - pastikan redirect URI sudah benar
2. Cek `.env` - pastikan GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET benar
3. Pastikan callback URL menggunakan HTTPS (bukan HTTP)

### Session/Cookie Issues

1. Pastikan `SESSION_DOMAIN=.zainzo.com` (dengan titik di depan)
2. Pastikan `SANCTUM_STATEFUL_DOMAINS=task.zainzo.com`
3. Pastikan frontend dan backend menggunakan HTTPS
4. Clear browser cookies dan coba lagi

---

## 📊 Monitoring

### Log Files

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Nginx logs
tail -f /var/log/nginx/error.log
tail -f /var/log/nginx/access.log

# Apache logs
tail -f /var/log/apache2/error.log
```

### Performance Monitoring

- Enable OPcache di PHP
- Monitor database query performance
- Setup queue workers jika menggunakan jobs:

```bash
php artisan queue:work --daemon
```

---

## 🔄 Update Process

Ketika ada update code di production:

```bash
# Backend
git pull origin main
composer install --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Frontend
git pull origin main
npm install
npm run build
# Upload dist/ ke server
```

---

## 📝 Notes

1. **Backup Database** secara regular:

```bash
mysqldump -u username -p zainzo_task > backup_$(date +%F).sql
```

2. **Environment Variables** sensitif jangan di-commit ke git (sudah di .gitignore)

3. **Monitor disk space** - Laravel logs bisa membesar:

```bash
# Truncate logs lama
echo "" > storage/logs/laravel.log
```

4. **Setup Cron** untuk scheduled tasks (jika ada):

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## ✅ Final Checklist

Backend:

- [ ] .env configured dengan domain produksi
- [ ] APP_DEBUG=false
- [ ] Database migrated
- [ ] Config cached
- [ ] SSL/HTTPS aktif
- [ ] File permissions correct
- [ ] Google OAuth configured

Frontend:

- [ ] Build untuk production
- [ ] Environment variables configured
- [ ] Deployed ke hosting
- [ ] DNS configured
- [ ] SSL/HTTPS aktif

Testing:

- [ ] Health check endpoint works
- [ ] Google OAuth login works
- [ ] CRUD operations work
- [ ] No console errors

---

**Good luck with deployment! 🚀**

Jika ada masalah, cek logs dan troubleshooting section di atas.
