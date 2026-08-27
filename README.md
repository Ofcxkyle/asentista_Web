# Asentista's Bakery & Coffee Web Application 🥖☕

A full-stack artisan bakery and coffee web application built with **Pure PHP, Vanilla CSS & JavaScript, and XAMPP MySQL**.

---

## 🌟 Key Features

### 1. 🛍️ Customer Storefront & Shopping Cart
- **Artisan Bread & Handcrafted Beverage Showcase**: Organic sourdoughs, rustic loaves, pastries, and daily roasts.
- **Interactive Multi-Item Shopping Cart**: Add items, adjust quantities, view order totals, and schedule pickup dates.
- **Live Search & Detail Modals**: Search the full bakery catalog with instant preview modals.
- **Table Booking & Custom Reservations**: Reserve baked items and dine-in tables connected to MySQL.

### 2. 🔐 Authentication & Role-Based Access
- **Guest Browsing**: Visitors can browse menus, preview cart totals, and inspect ingredients.
- **Account Protection**: Placing bookings and orders requires user registration or login.
- **Role Routing**: Administrators automatically land on the Operations Center, while customers access the storefront.

### 3. 👑 Executive Admin Operations Center (`admin.php`)
- **Real-Time KPI Dashboard**: Live revenue metrics (₱), pending order queue, baking schedule, and customer statistics.
- **Order Dispatch Board**: 1-click status transitions (`Pending` ➔ `Confirmed` ➔ `Completed` ➔ `Cancelled`) with keyword search and CSV export.
- **Menu Catalog Management (CRUD)**: Add, edit, and delete bakery items and beverage prices in MySQL.
- **Customer Directory**: View customer profiles, order counts, and lifetime spend.

---

## 🗄️ Database Setup (XAMPP MySQL)

1. Start **Apache** and **MySQL** in your **XAMPP Control Panel**.
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin/`).
3. Create a new database named `asentista_db`.
4. Import the provided [`database.sql`](database.sql) file.
5. Place this project inside `C:\xampp\htdocs\Asentista_Web`.
6. Open your browser and navigate to:
   ```
   http://localhost/Asentista_Web/
   ```

### ⚡ Default Demo Credentials
- **Admin**: `admin@asentista.com` / `admin123`
- **Customer**: `customer@asentista.com` / `password123`

---

## 🛠️ Technology Stack
- **Backend**: Pure PHP 8.x
- **Database**: MySQL / MariaDB (PDO with Prepared Statements)
- **Frontend**: Vanilla HTML5, CSS3, JavaScript (ES6+)
- **Design System**: Custom Bakery Palette (Espresso, Warm Amber, Cream) with Emil Kowalski motion curves.
