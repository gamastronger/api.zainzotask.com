# Struktur Database - Aplikasi Kanban

## Tabel yang Digunakan

### 1. **users**
Menyimpan data pengguna yang login dengan Google OAuth.
```
- id (primary key)
- google_id (unique)
- name
- email (unique)
- picture
- provider (default: 'google')
- remember_token
- created_at
- updated_at
```

### 2. **boards**
Menyimpan papan kanban milik user.
```
- id (primary key)
- user_id (foreign key → users.id)
- title
- description
- created_at
- updated_at
```
**Relasi**: Setiap board dimiliki oleh satu user (onDelete: cascade)

### 3. **columns**
Menyimpan kolom-kolom dalam papan kanban.
```
- id (primary key)
- board_id (foreign key → boards.id)
- title
- color
- position
- created_at
- updated_at
```
**Relasi**: Setiap column berada dalam satu board (onDelete: cascade)

### 4. **cards**
Menyimpan kartu tugas dalam kolom.
```
- id (primary key)
- column_id (foreign key → columns.id)
- title
- description
- image
- due_date
- completed (boolean)
- position
- created_at
- updated_at
```
**Relasi**: Setiap card berada dalam satu column (onDelete: cascade)

### 5. **card_labels**
Menyimpan label untuk kartu.
```
- id (primary key)
- card_id (foreign key → cards.id)
- label
- created_at
- updated_at
```
**Relasi**: Setiap label terkait dengan satu card (onDelete: cascade)

### 6. **personal_access_tokens**
Tabel sistem untuk Laravel Sanctum (autentikasi API).
```
- Digunakan untuk token autentikasi API
- Dikelola otomatis oleh Laravel Sanctum
```

### 7. **migrations**
Tabel sistem untuk tracking migration database.

---

## Hierarki Relasi

```
users (1)
  └── boards (*) 
       └── columns (*)
            └── cards (*)
                 └── card_labels (*)
```

**Keterangan:**
- (1) = one (satu)
- (*) = many (banyak)

**Cascade Delete:**
- Jika user dihapus → semua boards miliknya terhapus
- Jika board dihapus → semua columns di dalamnya terhapus
- Jika column dihapus → semua cards di dalamnya terhapus
- Jika card dihapus → semua labels di dalamnya terhapus

---

## Tabel yang Telah Dihapus

Tabel-tabel berikut tidak diperlukan untuk aplikasi kanban dan telah dihapus:

❌ **password_reset_tokens** - Tidak perlu (menggunakan Google OAuth)
❌ **sessions** - Tidak perlu (API stateless dengan Sanctum)
❌ **cache** & **cache_locks** - Tidak diperlukan untuk aplikasi sederhana
❌ **jobs**, **job_batches**, **failed_jobs** - Tidak menggunakan queue system
❌ **Baru** - Tabel test yang tidak jelas fungsinya

---

## Migration Files

1. `0001_01_01_000000_create_users_table.php` - Buat tabel users
2. `2025_12_02_111233_add_google_fields_to_users_table.php` - Tambah kolom Google OAuth
3. `2025_12_02_111240_create_boards_table.php` - Buat tabel boards
4. `2025_12_02_111241_create_columns_table.php` - Buat tabel columns
5. `2025_12_02_111242_create_cards_table.php` - Buat tabel cards
6. `2025_12_02_111243_create_card_labels_table.php` - Buat tabel card_labels
7. `2025_12_30_000001_drop_email_verified_at_and_password_from_users_table.php` - Hapus kolom tidak perlu
8. `2025_12_30_000002_drop_unnecessary_tables.php` - Hapus tabel tidak diperlukan

---

## Keamanan & Autentikasi

- **Autentikasi**: Google OAuth (via Laravel Socialite)
- **API Protection**: Laravel Sanctum dengan stateful authentication
- **Authorization**: Setiap board hanya bisa diakses oleh pemiliknya (user_id)

---

## Status Database

✅ Database sudah optimal untuk aplikasi kanban
✅ Semua relasi terhubung dengan baik
✅ Cascade delete sudah dikonfigurasi
✅ Struktur clean tanpa tabel yang tidak diperlukan
