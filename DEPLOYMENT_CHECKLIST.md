# Checklist Wdrożenia na Hosting - Bloxer Platform

## 🔧 Konfiguracja Środowiska

### [ ] PHP i Serwer
- [ ] PHP 8.0+ (zalecane 8.1+)
- [ ] Rozszerzenia PHP: mysqli, PDO, mbstring, json, curl, gd, zip
- [ ] Apache/Nginx z mod_rewrite lub odpowiednik
- [ ] SSL/TLS (HTTPS)

### [ ] Baza Danych
- [ ] MySQL 5.7+ lub MariaDB 10.2+
- [ ] Uprawnienia do tworzenia baz danych
- [ ] Import struktury bazy danych

### [ ] Pliki Konfiguracyjne
- [ ] `.env` - ustawienia produkcyjne
- [ ] `config/database.php` - dane dostępowe do bazy
- [ ] `.htaccess` - reguły rewrite i bezpieczeństwa

## 🗄️ Baza Danych

### [ ] Tabele
- [ ] `users` - konta użytkowników
- [ ] `login_attempts` - ochrona przed brute force
- [ ] `remember_tokens` - remember me
- [ ] `apps` - aplikacje
- [ ] `app_versions` - wersje aplikacji
- [ ] `user_apps` - zainstalowane aplikacje
- [ ] `comments` - komentarze
- [ ] `ratings` - oceny
- [ ] `notifications` - powiadomienia

### [ ] Dane Testowe (opcjonalnie)
- [ ] Konta deweloperskie (john@dev.com, sarah@dev.com, mike@dev.com)
- [ ] Przykładowe aplikacje

## 📁 Struktura Plików

### [ ] Katalogi Główne
- [ ] `controllers/` - logika aplikacji
- [ ] `views/` - szablony (jeśli używane)
- [ ] `assets/` - CSS, JS, obrazy
- [ ] `config/` - konfiguracja
- [ ] `models/` - modele danych
- [ ] `uploads/` - pliki użytkowników (uprawnienia 755)
- [ ] `logs/` - logi (uprawnienia 755)

### [ ] Pliki Startowe
- [ ] `index.php` - główny router
- [ ] `bootstrap.php` - inicjalizacja
- [ ] `.htaccess` - reguły URL

## 🔐 Bezpieczeństwo

### [ ] Konfiguracja PHP
- [ ] `display_errors = Off` (produkcja)
- [ ] `error_reporting = E_ALL & ~E_DEPRECATED`
- [ ] `expose_php = Off`
- [ ] `allow_url_fopen = Off` (jeśli nie potrzebne)

### [ ] Ochrona Danych
- [ ] Hasła zahashowane (password_hash)
- [ ] CSRF tokeny aktywne
- [ ] Input validation
- [ ] SQL injection protection (prepared statements)
- [ ] XSS protection (htmlspecialchars)

### [ ] Pliki i Uprawnienia
- [ ] `.env` niedostępny z zewnątrz (chmod 600)
- [ ] Katalogi uploads/logs zabezpieczone
- [ ] Brak plików .bak, .old w publicznym dostępie

### [ ] Nagłówki HTTP
- [ ] X-Frame-Options
- [ ] X-Content-Type-Options
- [ ] X-XSS-Protection
- [ ] Strict-Transport-Security (HTTPS)

## 🌐 Funkcjonalność

### [ ] Autentykacja
- [ ] Logowanie (email/nazwa użytkownika)
- [ ] Rejestracja
- [ ] Reset hasła
- [ ] Remember me
- [ ] Wylogowanie
- [ ] CSRF protection

### [ ] Role Użytkowników
- [ ] Użytkownik (przeglądanie aplikacji)
- [ ] Developer (tworzenie aplikacji)
- [ ] Przekierowania po logowaniu

### [ ] Aplikacje
- [ ] Przeglądanie marketplace
- [ ] Szukanie aplikacji
- [ ] Instalowanie aplikacji
- [ ] Oceny i komentarze
- [ ] Edytor kodu (Monaco)

### [ ] Strony Statyczne
- [ ] Regulamin (terms.php)
- [ ] Polityka prywatności (privacy.php)
- [ ] Strona główna

## 🎨 Frontend

### [ ] CSS i Assets
- [ ] Style.css załadowane
- [ ] Reboot.css załadowane
- [ ] Font Awesome icons
- [ ] Responsywność (mobile-friendly)

### [ ] JavaScript
- [ ] Formularze działają
- [ ] Walidacja po stronie klienta
- [ ] AJAX (jeśli używany)
- [ ] Brak błędów w konsoli

## 📤 Formularze i Interakcje

### [ ] Formularz Logowania
- [ ] Walidacja pól
- [ ] Przekierowanie po sukcesie
- [ ] Komunikaty błędów
- [ ] Linki do regulaminu/polityki

### [ ] Formularz Rejestracji
- [ ] Walidacja email
- [ ] Sprawdzanie unikalności
- [ ] Potwierdzenie hasła

### [ ] Reset Hasła
- [ ] Wysyłanie tokena
- [ ] Weryfikacja tokena
- [ ] Ustawianie nowego hasła

## 🔄 Przekierowania i Routing

### [ ] URL Rewriting
- [ ] `/login` → `controllers/auth/login.php`
- [ ] `/register` → `controllers/auth/register.php`
- [ ] `/dashboard` → `controllers/core/dashboard.php`
- [ ] `/marketplace` → `controllers/marketplace/marketplace.php`

### [ ] Błędy 404
- [ ] Custom error page
- [ ] Brak błędnych przekierowań

## 📊 Monitoring i Logi

### [ ] Logi Aplikacji
- [ ] Logi błędów PHP
- [ ] Logi bezpieczeństwa
- [ ] Logi logowań

### [ ] Monitoring
- [ ] Uptime monitoring
- [ ] Performance monitoring
- [ ] Error tracking

## 🚀 Optymalizacja

### [ ] Wydajność
- [ ] Cache (jeśli używany)
- [ ] Kompresja Gzip
- [ ] Optymalizacja obrazów
- [ ] Minify CSS/JS

### [ ] SEO
- [ ] Meta tags
- [ ] Open Graph
- [ ] Sitemap (jeśli potrzebny)
- [] Robots.txt

## 📱 Testowanie

### [ ] Testy Funkcjonalne
- [ ] Logowanie wszystkich kont testowych
- [ ] Rejestracja nowego użytkownika
- [ ] Reset hasła
- [ ] Przeglądanie aplikacji
- [ ] Przekierowania

### [ ] Testy Kompatybilności
- [ ] Chrome/Chromium
- [ ] Firefox
- [ ] Safari
- [ ] Mobile (iOS/Android)

### [ ] Testy Wydajności
- [ ] Czas ładowania strony < 3s
- [ ] PageSpeed Insights
- [ ] GTmetrix

## 🔧 Konfiguracja Produkcyjna

### [ ] .env Production
```env
APP_ENV=production
DB_HOST=twoj_host_bazy_danych
DB_USER=uzytkownik_bazy
DB_PASS=haslo_bazy
DB_NAME=nazwa_bazy
ENABLE_DEBUG=false
LOG_LEVEL=error
RATE_LIMIT_ENABLED=true
CSRF_PROTECTION=true
APP_URL=https://twojadomena.com
UPLOAD_PATH=uploads/
SESSION_LIFETIME=3600
```

### [ ] .htaccess Security
```apache
# Block access to sensitive files
<Files ~ "^\.">
    Order allow,deny
    Deny from all
</Files>

<FilesMatch "^\.env">
    Order allow,deny
    Deny from all
</FilesMatch>

# Prevent directory listing
Options -Indexes

# Security headers
<IfModule mod_headers.c>
    Header always set X-Frame-Options DENY
    Header always set X-Content-Type-Options nosniff
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

## 📋 Finalna Weryfikacja

### [ ] Przed Wdrożeniem
- [ ] Backup lokalnej wersji
- [ ] Test na staging (jeśli możliwe)
- [ ] Weryfikacja zależności
- [ ] Sprawdzenie licencji

### [ ] Po Wdrożeniu
- [ ] Test wszystkich funkcji
- [ ] Sprawdzenie logów
- [ ] Monitorowanie wydajności
- [ ] Testy bezpieczeństwa

---

## 🚨 Krytyczne Problemy do Naprawienia Przed Wdrożeniem

1. **Warningi PHP w CLI** - tylko w środowisku deweloperskim
2. **Session management** - poprawić reset sesji w testach
3. **Error reporting** - upewnić się, że jest wyłączony na produkcji
4. **File permissions** - sprawdzić uprawnienia katalogów uploads/logs
5. **Environment variables** - upewnić się, że .env nie jest dostępny publicznie

## 📞 Kontakt Wsparcia
- **Discord:** hmm067
- **Email:** (skonfigurować w .env)

---

*Last updated: 30 kwietnia 2026*
