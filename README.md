# Asentista's Bakery Web Application

A web application for Asentista's Bakery built using PHP, MySQL, HTML, CSS, and JavaScript. The system allows customers to view bakery products, manage a shopping cart, and place orders or table reservations, while providing an admin panel to manage products, orders, and customer records.

## Features

### Customer
- **Browse Products**: View breads, pastries, and drinks with prices and descriptions.
- **Shopping Cart**: Add items to cart, update quantities, remove items, and proceed to checkout.
- **Order & Reservations**: Place orders for in-store pickup, direct delivery, or table reservations with custom notes and payment options (Cash on Delivery, GCash, Bank Transfer).
- **Order Confirmation**: View order details and reference number right after purchasing.
- **Customer Account**: Sign up, log in, view personal order history, and reset password if forgotten.

### Admin
- **Dashboard**: View summary stats including total revenue, order count, and pending orders.
- **Order Management**: Review incoming orders, update status (Pending, Confirmed, Completed, Cancelled), and export order lists to CSV.
- **Product Management**: Add new products, update prices and stock levels, or toggle visibility on the storefront.
- **Customer List**: View registered customer profiles and order counts.

## Folder Structure

```
Asentista_Web/
├── index.php               # Main storefront and menu
├── style.css               # Main stylesheet
├── script.js               # Frontend JavaScript
├── .gitignore              # Git ignore file
├── README.md               # Project documentation
│
├── Admin/
│   └── admin.php           # Admin dashboard and controls
│
├── Cart/
│   ├── cart.php            # Cart and checkout page
│   ├── cart_action.php     # Handles cart updates
│   └── order_process.php   # Handles order submissions
│
├── Login/
│   ├── auth.php            # Login and registration page
│   ├── forgot_password.php # Password recovery request
│   ├── reset_password.php  # Reset password page
│   ├── login.php           # Login redirect helper
│   └── logout.php          # Logout script
│
├── User/
│   └── dashboard.php       # Customer order history and profile
│
├── database/
│   ├── config.php          # Database connection and settings
│   ├── function.php        # Core backend functions
│   ├── validation.php      # Input validation helpers
│   ├── success.php         # Order receipt page
│   └── database.sql        # Database schema and seed data
│
└── assets/                 # Images and logo
```

## Technologies Used

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP
- **Database**: MySQL (XAMPP)

## Setup and Installation

1. Copy the project folder into your XAMPP `htdocs` directory:
   `c:\xampp\htdocs\Asentista_Web`
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Import `database/database.sql` into phpMyAdmin (`http://localhost/phpmyadmin`), or access the site directly to allow automatic table creation.
4. Open your browser and go to:
   `http://localhost/Asentista_Web`

### Default Login Accounts
- **Admin**: `admin@asentista.com` / `admin123`
- **Customer**: `customer@asentista.com` / `password123`
