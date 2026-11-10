# 🍞 Asentista's Bakery — Symfony 6.4 + Docker

A fully production-ready bakery ordering system migrated from legacy PHP/MySQL to **Symfony 6.4 LTS**, containerized with **Docker Compose**, featuring a normalized relational MySQL 8.0 database, real email via **Mailpit**, and a complete admin panel.

---

## 🚀 Quick Start

### Prerequisites
- Docker Desktop (Windows)
- Docker Compose v2

### Start All Containers

```bash
docker compose up -d --build
```

### Access Points

| Service | URL | Credentials |
|---------|-----|-------------|
| 🌐 Web App | http://localhost:8000 | — |
| 🗄️ phpMyAdmin | http://localhost:8088 | root / rootpassword |
| 📬 Mailpit (Email UI) | http://localhost:8025 | — |
| 🔌 MySQL (external) | localhost:3307 | root / rootpassword |

---

## 🔑 Default Accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@asentista.com | admin123 |
| Customer | customer@asentista.com | password123 |

---

## 🐳 Docker Containers

| Container | Image | Purpose |
|-----------|-------|---------|
| `asentista_app` | PHP 8.2-FPM Alpine | Symfony application |
| `asentista_web` | Nginx 1.25 Alpine | Reverse proxy |
| `asentista_db` | MySQL 8.0 | Database |
| `asentista_phpmyadmin` | phpMyAdmin | Database GUI |
| `asentista_mailer` | Mailpit | Email testing UI |

---

## 🏗️ Architecture

```
Browser → Nginx:8000 → PHP-FPM:9000 → MySQL:3306
                                     → Mailpit:1025 (email)
```

### Database Relations (All FK-constrained)
```
categories ← products ← cart_items ← users
                       ← order_items ← orders ← users
password_resets ← users
```

---

## 🔧 Useful Docker Commands

```bash
# Clear & warmup Symfony cache
docker exec asentista_app sh -c "chmod -R 777 /var/www/html/var && php bin/console cache:clear && php bin/console cache:warmup"

# Run Doctrine migrations
docker exec asentista_app php bin/console doctrine:migrations:migrate --no-interaction

# Check all routes
docker exec asentista_app php bin/console debug:router

# Install Composer dependencies (e.g., after adding mailer)
docker exec asentista_app composer install --no-dev --optimize-autoloader

# View Symfony logs
docker exec asentista_app tail -f /var/www/html/var/log/dev.log

# MySQL shell
docker exec -it asentista_db mysql -uroot -prootpassword asentista_bakery_db
```

---

## 📦 Feature Checklist

### Customer Features
- [x] Browse storefront with featured products
- [x] Full product menu with category & search filters
- [x] Add to cart (session-based for guests, DB-persisted for logged-in users)
- [x] Cart merge on login
- [x] Checkout: In-Store Pickup / Direct Delivery / Table Reservation
- [x] Order confirmation page with printable receipt
- [x] Order confirmation email (via Mailpit)
- [x] Customer dashboard with order history
- [x] Order detail view
- [x] Profile edit (name, phone, password change)
- [x] User registration + Login + Logout
- [x] Forgot password with email link (via Mailpit)
- [x] Password reset via token (1-hour expiry)

### Admin Features
- [x] Admin dashboard: KPIs (revenue, orders, pending, completed)
- [x] Recent orders & low-stock alerts
- [x] Full orders list with status update (Pending → Confirmed → Completed → Cancelled)
- [x] CSV export of all orders
- [x] Product catalog with Add, Edit (modal), Delete, Toggle Featured
- [x] Customer list
- [x] CSRF protection on all state-changing forms
- [x] Admin sticky topbar when logged in as admin

---

## 🗄️ Database Schema

```sql
categories       -- Product categories (Bread, Beverage)
users            -- Symfony-compatible users (JSON roles)
products         -- Menu items linked to categories (FK)
cart_items       -- Session & user carts (FK to users, products)
orders           -- Customer orders (FK to users, ON DELETE SET NULL)
order_items      -- Order line items (FK to orders, products)
password_resets  -- Token-based password reset (FK to users)
```

---

## 📧 Email Testing

All emails are caught by **Mailpit** — no real emails are sent in development.

1. Visit http://localhost:8025 to view the Mailpit inbox
2. Try Forgot Password from http://localhost:8000/forgot-password
3. Place an order and check for the confirmation email

---

## 🔐 Security

- Symfony Security with Entity UserProvider
- bcrypt password hashing
- CSRF tokens on all state-changing POST forms
- Role hierarchy: `ROLE_ADMIN` inherits `ROLE_CUSTOMER`
- Access control: `/admin/*` → ROLE_ADMIN, `/account/*` → ROLE_CUSTOMER
- Password reset anti-enumeration (same flash message regardless of email existence)
- Remember Me cookie (1 week)
