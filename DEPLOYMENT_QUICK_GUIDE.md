# 🚀 Quick Deployment Guide

## Perubahan yang Sudah Dilakukan

### 1. Environment Configuration (`.env`)
```env
# Production
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.zainzo.com
FRONTEND_URL=https://task.zainzo.com
GOOGLE_REDIRECT_URI=https://api.zainzo.com/auth/google/callback
SESSION_DOMAIN=.zainzo.com
SANCTUM_STATEFUL_DOMAINS=task.zainzo.com

# Development (commented out)
# APP_ENV=local
# APP_DEBUG=true
# APP_URL=http://localhost:8000
# FRONTEND_URL=http://localhost:5173
# GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
# SESSION_DOMAIN=localhost
# SANCTUM_STATEFUL_DOMAINS=localhost:5173
```

### 2. CORS Configuration (`config/cors.php`)
```php
'allowed_origins' => [
    'https://task.zainzo.com',      // Production
    // 'http://localhost:5173',      // Development
],
```

### 3. Domain Configuration
- **Frontend:** `https://task.zainzo.com`
- **Backend:** `https://api.zainzo.com`

---

## 📝 Yang Perlu Dilakukan Sebelum Deploy

### 1. Update Database Credentials
Edit `.env`:
```env
DB_HOST=your_production_db_host
DB_DATABASE=your_production_db_name
DB_USERNAME=your_production_db_user
DB_PASSWORD=your_production_db_password
```

### 2. Generate New APP_KEY (WAJIB!)
```bash
php artisan key:generate
```

### 3. Update Google OAuth
Di **Google Cloud Console**, tambahkan:
- Authorized JavaScript origins: `https://task.zainzo.com`
- Authorized redirect URIs: `https://api.zainzo.com/auth/google/callback`

### 4. Run Migrations
```bash
php artisan migrate --force
```

### 5. Cache Configuration (Performance)
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 🔄 Untuk Kembali ke Development Mode

Uncomment baris development di `.env` dan comment baris production:
```env
# APP_ENV=production
APP_ENV=local

# APP_DEBUG=false
APP_DEBUG=true

# APP_URL=https://api.zainzo.com
APP_URL=http://localhost:8000
```

Di `config/cors.php`, uncomment localhost:
```php
'allowed_origins' => [
    // 'https://task.zainzo.com',
    'http://localhost:5173',
],
```

Lalu clear cache:
```bash
php artisan config:clear
php artisan cache:clear
```

---

## 📚 Dokumentasi Lengkap

Lihat **DEPLOYMENT_CHECKLIST.md** untuk:
- ✅ Checklist lengkap deployment
- 🔧 Konfigurasi web server (Nginx/Apache)
- 🔒 Security checklist
- 🐛 Troubleshooting guide
- 📊 Monitoring dan maintenance

---

## ⚠️ PENTING!

1. **Jangan lupa:** `APP_DEBUG=false` di production
2. **Generate:** APP_KEY baru untuk production
3. **Update:** Google OAuth redirect URIs
4. **Backup:** Database sebelum migrate di production
5. **Test:** Semua fitur setelah deploy

---

## 🆘 Quick Troubleshooting

**CORS Error:**
```bash
php artisan config:clear && php artisan config:cache
```

**500 Error:**
```bash
# Cek permissions
chmod -R 755 storage bootstrap/cache

# Cek logs
tail -f storage/logs/laravel.log
```

**Session/Auth Issues:**
Pastikan `SESSION_DOMAIN=.zainzo.com` (dengan titik di depan!)

---

Semua code lama sudah di-comment, jadi aman untuk switch antara dev dan production! 🎉
