# Asentista's Bakery & Coffee Web Application 🥖☕

A full-stack artisan bakery and coffee web application built with **Pure PHP, Vanilla CSS, Vanilla JavaScript, and XAMPP MySQL** featuring an interactive customer storefront, shopping cart, order reservation system, product catalog management, and admin operations dashboard.

---

## 🌟 Key Features Walkthrough

### 1. 🛍️ Customer Storefront & Cart (`index.php`, `Cart/cart.php`)
- **Artisan Showcase**: Browse organic sourdoughs, rustic baguettes, croissants, and specialty cold brews.
- **Interactive Cart**: Instant AJAX add-to-cart, debounce-protected quantity steppers (`+` / `-`), item removal, and subtotal calculation.
- **Table Booking & Special Orders**: Custom date picker, order preference (In-Store Pickup, Dine-in Table Booking, Direct Delivery), and baking instructions.
- **Printable Order Receipt (`database/success.php`)**: Clean confirmation view with automated print-styling.

### 2. 🔐 Authentication Portal (`Login/auth.php`)
- **Role Routing**: Administrators land on the Executive Operations Center; customers land on the storefront or personal dashboard.
- **Guest Exploration**: Visitors can explore menus, preview items, and calculate orders; authentication is required to confirm bookings.

### 3. 👑 Executive Admin Operations Center (`Admin/admin.php`)
- **Real-Time KPIs**: Live revenue counter (₱), total orders, pending confirmation queue, and low-stock alerts.
- **Order Dispatch Board**: 1-click status transitions (`Pending` ➔ `Confirmed` ➔ `Completed` ➔ `Cancelled`) with live keyword search and instant CSV export.
- **Product Catalog CRUD**: Add items with photo upload or preset choices, edit prices and stock, toggle storefront visibility, and restock inventory.
- **Customer Directory**: View customer profiles, lifetime order counts, and total spend.

### 4. 👤 Customer Order Tracker (`User/dashboard.php`)
- **Personalized Metrics**: View personal order history, current preparation status, and dates.
- **Order Search & Filter**: Filter personal orders by status and export personal order history to CSV.

---

## 📁 Repository Directory Structure

```
Asentista_Web/
├── .gitignore              # Curated repository hygiene rules
├── README.md               # Project overview & documentation
├── index.php               # Customer storefront, catalog & booking modal
├── style.css               # Design system tokens, responsive layout, dark luxury theme
├── script.js               # Interactive UI controller, cart AJAX, modal handlers
│
├── Admin/
│   └── admin.php           # Executive Operations Center (KPIs, Dispatch Board, Catalog CRUD)
│
├── Cart/
│   ├── cart.php            # Multi-item shopping cart & checkout portal
│   ├── cart_action.php     # CSRF-protected AJAX endpoint for cart mutations
│   └── order_process.php   # Order creation & table reservation processor
│
├── Login/
│   ├── auth.php            # Authentication portal (Sign In & Register)
│   ├── login.php           # Clean alias redirector to auth.php
│   └── logout.php          # Session termination & cookie flush
│
├── User/
│   └── dashboard.php       # Customer order tracker & personal order history
│
├── database/
│   ├── config.php          # Database PDO connection, security flags & error handlers
│   ├── function.php        # Core CRUD helper library, inventory reconciliation & auth
│   ├── validation.php      # Dual-layer validation, XSS sanitization, CSRF & rate-limiting
│   ├── success.php         # Printable order receipt confirmation
│   └── database.sql        # Complete MySQL database migration and seed script
│
└── assets/                 # High-resolution bakery photography & brand assets
```

---

## 🛠️ Technology Stack
- **Backend**: Pure PHP 8.x (PSR-12 compliant, object-oriented PDO)
- **Database**: MySQL / MariaDB (InnoDB, Foreign Keys with Cascade/Set Null, B-tree Composite Indexes)
- **Frontend**: Vanilla HTML5, Vanilla CSS3 (Custom Design Tokens, Flexbox & CSS Grid), Vanilla JavaScript (ES6+ Fetch API)
- **Security**: Cryptographic CSRF tokens, Bcrypt password hashing (`PASSWORD_DEFAULT`), Session Fixation immunity (`session_regenerate_id`), XSS output escaping (`ENT_QUOTES`), and Brute-Force lockout defense.

