# InfinityFree Deployment Guide

## 🚀 Szybki start

### 1. Konto InfinityFree
1. Zarejestruj się na [InfinityFree](https://infinityfree.net)
2. Stwórz nowe konto hostingowe
3. Zanotuj swoje dane bazy danych z panelu

### 2. Konfiguracja bazy danych
W panelu InfinityFree:
- Znajdź **MySQL Database**
- Skopiuj dane:
  - Host: `sql###.epizy.com`
  - Username: `epiz_######`
  - Password: Twoje hasło
  - Database: `epiz_######_bloxer`

### 3. Edytuj config/infinityfree.php
```php
define('DB_HOST', 'sql###.epizy.com');  // Wklej swoje
define('DB_USER', 'epiz_######');       // Wklej swoje
define('DB_PASS', 'password');           // Wklej swoje
define('DB_NAME', 'epiz_######_bloxer'); // Wklej swoje
```

### 4. Import bazy danych
1. W panelu InfinityFree otwórz **phpMyAdmin**
2. Importuj plik `database/complete_database_schema.sql`
3. Sprawdź czy tabele zostały utworzone

### 5. Upload plików
**Metoda 1 - File Manager:**
1. Zaloguj się do panelu InfinityFree
2. Otwórz **File Manager**
3. Upload wszystkie pliki oprócz:
   - `railway.*`
   - `nixpacks.toml`
   - `.dockerignore`
   - `RAILWAY_DEPLOY.md`

**Metoda 2 - FTP:**
1. Pobierz dane FTP z panelu
2. Użyj FileZilla lub WinSCP
3. Upload projektu do `/htdocs`

### 6. Ustawienia
- Upewnij się że `logs/` i `uploads/` mają chmod 755
- Sprawdź czy `.htaccess` jest na serwerze
- Testuj dostęp do strony

## 🔧 Konfiguracja

### Wymagania InfinityFree:
- ✅ PHP 7.4+ (mają 8.0+)
- ✅ MySQL 5.7+ (mają)
- ✅ 400MB disk space
- ✅ Brak limitu transferu

### Co nie działa:
- ❌ Composer (brak na serwerze)
- ❌ WebSocket (brak wsparcia)
- ❌ Queue jobs (brak background process)

### Alternatywy:
- 📦 **Composer:** Użyj vendor folder z lokalnego buildu
- 🔌 **WebSocket:** Użyj long polling lub SSE
- 📋 **Queue:** Użyj cron jobs lub scheduled tasks

## 🛠️ Rozwiązywanie problemów

### Błąd 500 - Internal Server Error
```bash
# Sprawdź logi w panelu InfinityFree
# Upewnij się że .htaccess jest poprawny
# Sprawdź uprawnienia plików
```

### Błąd bazy danych
```bash
# Sprawdź dane w config/infinityfree.php
# Upewnij się że baza została zaimportowana
# Testuj połączenie w phpMyAdmin
```

### Błąd uploadu plików
```bash
# Sprawdź uprawnienia folderu uploads/
# Ustaw chmod 755 lub 777
# Sprawdź limity w .htaccess
```

## 📱 Testowanie

### Po deployment:
1. Otwórz `https://yourname.infinityfree.app`
2. Spróbuj zarejestrować konto
3. Zaloguj się jako developer
4. Stwórz pierwszy projekt

## 🔄 Aktualizacje

### Jak aktualizować:
1. Upload nowych plików przez FTP
2. Importuj nowe migracje SQL
3. Wyczyść cache (usuń vendor/autoload.php)

## 💡 Tips

### Optymalizacja:
- Użyj minimalnej liczby plików
- Kompresuj CSS/JS
- Użyj cache headers w .htaccess

### Bezpieczeństwo:
- Zmień ENCRYPTION_KEY
- Ustaw silne hasła
- Ukryj pliki .env

### Monitorowanie:
- Sprawdzaj logi regularnie
- Monitoruj transfer
- Backup bazy danych

---

## 🎉 Gotowe!

Twój Bloxer działa na InfinityFree! 🚀

Jeśli masz problemy, sprawdź:
1. Logi w panelu InfinityFree
2. Konfigurację bazy danych
3. Uprawnienia plików
