# AgriMarket

**Fresh from the farm to your table.**

AgriMarket is a lightweight, framework-free PHP and MySQL marketplace application designed to connect smallholder farmers directly with buyers. Farmers can effortlessly manage their produce listings, buyers can search, filter, order, and print receipts, and administrators have complete oversight to verify farms and manage orders.

![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777bb3) ![MySQL 8 / MariaDB 10.4+](https://img.shields.io/badge/MySQL-8%20%7C%20MariaDB-00758f) ![Tailwind CSS](https://img.shields.io/badge/Tailwind-CDN-38bdf8)

---

## Table of Contents

1. [Features](#features)
2. [Tech Stack](#tech-stack)
3. [Project Structure](#project-structure)
4. [Requirements](#requirements)
5. [Installation](#installation)
6. [Configuration](#configuration)
7. [Login Credentials](#login-credentials)
8. [Pages and Routes](#pages-and-routes)
9. [JSON API](#json-api)
10. [Business Rules](#business-rules)
11. [Security](#security)
12. [Production Checklist](#production-checklist)
13. [Troubleshooting](#troubleshooting)
14. [Documentation](#documentation)

---

## Features

### For Buyers

- **Live Product Catalogue:** Filter produce instantly by keyword, category, max price, and farm location without reloading the page.
- **Detailed Product Info:** View harvest dates, farm origin, stock levels, and item details.
- **Session-Based Cart:** Add products and adjust quantities safely—prices are verified directly against the database.
- **Flexible Checkout:** Order with delivery details and choose payment options (M-Pesa or Cash on Delivery).
- **Order History & Receipts:** Access past orders and generate printable transaction receipts.

### For Farmers

- **Easy Registration:** Register with a dedicated farm name and location.
- **Produce Dashboard:** Add, edit, hide, show, or delete produce listings.
- **Order Management:** View line-item buyer orders with contact info and delivery locations.
- **Public Farm Profile:** Show public buyers all active listings on a dedicated farm page.

### For Administrators

- **System Metrics:** Monitor overall users, farmers, products, total orders, and site revenue.
- **Order Oversight:** Manage order progress and payment statuses (cancelling an order restores inventory).
- **Moderation:** Verify or revoke farmer status, toggle product visibility, and review contact messages.

---

## Tech Stack

| Layer          | Technology                                                                 |
| :------------- | :------------------------------------------------------------------------- |
| **Backend**    | PHP 8.1+ (PDO, native sessions, framework-free)                            |
| **Database**   | MySQL 8.0+ or MariaDB 10.4+ (InnoDB, `utf8mb4`)                            |
| **Frontend**   | Tailwind CSS (CDN), Font Awesome 6, Google Fonts (Inter, Playfair Display) |
| **JavaScript** | Vanilla JS (No build pipeline required)                                    |

---

## Project Structure

```text
AGRIMARKET/
├── api/
│   └── produce.php          # JSON listing endpoint powering live catalogue filters
├── assets/
│   ├── app.js               # Handles mobile menu, steppers, live filters, confirmations
│   └── style.css            # Custom styling complement to Tailwind
├── database/
│   ├── schema.sql           # Core database schema, tables, and category seeds
│   └── seed.php             # CLI script for seeding demo farmers, buyers, and products
├── docs/
│   ├── WIREFRAMES.md        # Screen and UI layouts
│   └── ERD.md               # Entity Relationship Diagram
├── about.php                # About page with live statistics
├── admin.php                # Administrator control center
├── cart.php                 # Shopping cart view
├── catalogue.php            # Produce catalogue with live filters
├── checkout.php             # Delivery details and order placement
├── config.php               # Core settings, database credentials, and session setup
├── contact.php              # Public contact form
├── farmer_profile.php       # Public farm page
├── farmer.php               # Farmer management dashboard
├── farmers.php              # Directory of registered farms
├── footer.php               # Shared page footer component
├── functions.php            # Database, auth, CSRF, cart, and catalogue helper functions
├── header.php               # Shared page header and navigation menu
├── index.php                # Homepage
├── login.php                # User sign-in portal
├── logout.php               # Secure logout handler (POST request)
├── orders.php               # Buyer order history
├── product.php              # Individual product details page
├── profile.php              # User account and password settings
├── receipt.php              # Printable order receipt
├── register.php             # Account creation for buyers or farmers
└── setup_admin.php          # One-time setup script for initial administrator
```

---

## Requirements

- **PHP 8.1+** with `pdo_mysql`, `mbstring`, and `session` extensions enabled.
- **MySQL 8.0+** or **MariaDB 10.4+**.
- Web server (Apache, Nginx, or PHP's built-in development server).
- Internet connection in browser to fetch CDNs (Tailwind CSS, Font Awesome, Google Fonts, demo images).

---

## Installation

### 1. Extract Project

Place the `AGRIMARKET/` directory into your web server root (e.g., `htdocs/` or `/var/www/html/`).

### 2. Import Database

Import `schema.sql` into MySQL:

```bash
mysql -u root -p < database/schema.sql
```

### 3. Configure Database Credentials

Edit `config.php` or set your environment variables:

```bash
export DB_HOST=127.0.0.1
export DB_NAME=agrimarket
export DB_USER=root
export DB_PASS='your_database_password'
```

### 4. Seed Demo Data (Optional)

Run the CLI seed script to add sample produce, farms, and users:

```bash
php database/seed.php
```

### 5. Create Administrator

1. Set an `ADMIN_SETUP_KEY` in `config.php` or as an environment variable.
2. Visit `http://localhost:8000/setup_admin.php` in your browser.
3. Fill out the form and submit.
4. **Delete `setup_admin.php`** immediately after registration.

### 6. Launch Server

```bash
php -S localhost:8000
```

Visit `http://localhost:8000` in your browser.

---

## Configuration

Settings are managed in `config.php`:

| Setting               | Default Value                       | Description                                        |
| :-------------------- | :---------------------------------- | :------------------------------------------------- |
| `APP_NAME`            | `AgriMarket`                        | Brand name displayed across site                   |
| `APP_TAGLINE`         | `Fresh from the farm to your table` | Platform tagline                                   |
| `CURRENCY`            | `KES`                               | Currency unit for item prices                      |
| `DELIVERY_FEE`        | `150`                               | Flat fee added to each order                       |
| `PER_PAGE`            | `8`                                 | Items per catalogue page                           |
| `SUPPORT_EMAIL`       | `support@agrimarket.co.ke`          | Support email displayed on receipts/contact page   |
| `SUPPORT_PHONE`       | `+254 700 123 456`                  | Support hotline displayed on receipts/contact page |
| `ADMIN_SETUP_KEY`     | `change-this-key`                   | Security key required for initial admin setup      |
| `DB_HOST` / `DB_NAME` | `127.0.0.1` / `agrimarket`          | Database connection host and database name         |
| `DB_USER` / `DB_PASS` | `root` / `""`                       | Database username and password                     |

---

## Login Credentials

> **Notice:** Keep real passwords out of version control. Enter your test accounts below:

| Role                 | Username / Email     | Password       | Notes                                |
| :------------------- | :------------------- | :------------- | :----------------------------------- |
| **Administrator**    | `chirchir@gmail.com` | `qwerty12`     | Created via `setup_admin.php`        |
| **Farmer**           | `jane@gmail.com`     | `Jane12345678` | Demo account or registered farmer    |
| **Buyer**            | `espstein@gmail.com` | `qwerty@20.`   | Demo account or registered buyer     |
| **Database (MySQL)** | ` `                  | ` `            | Same values as `DB_USER` / `DB_PASS` |

_Password Requirement:_ Minimum 8 characters containing at least one letter and one number.

---

## Pages and Routes

| Page Route                  | Access        | Function                                                          |
| :-------------------------- | :------------ | :---------------------------------------------------------------- |
| `index.php`                 | Public        | Homepage featuring hero section, catalogue preview, process guide |
| `catalogue.php`             | Public        | Full catalogue with real-time filters and search                  |
| `product.php?id=`           | Public        | Product overview and purchase selection                           |
| `farmers.php`               | Public        | List of registered farms                                          |
| `farmer_profile.php?id=`    | Public        | Profile view for individual farms and their inventory             |
| `about.php`, `contact.php`  | Public        | Platform info and contact submission form                         |
| `cart.php`                  | Public        | Active shopping cart overview                                     |
| `register.php`, `login.php` | Guest         | User registration and authentication portals                      |
| `checkout.php`              | Authenticated | Delivery info input and order submission                          |
| `receipt.php?id=`           | Owner / Admin | Transaction receipt display and printing                          |
| `orders.php`, `profile.php` | Authenticated | Buyer order tracking and account settings                         |
| `farmer.php`                | Farmer        | Listing management center and order fulfillment view              |
| `admin.php`                 | Admin         | Administrative oversight for platform metrics, users, and content |
| `logout.php`                | Authenticated | Form post endpoint for terminating sessions securely              |
| `setup_admin.php`           | Temporary     | Initial administrator creation utility                            |

---

## JSON API

`GET /api/produce.php` returns active produce items as JSON to supply the live filtering component.

### Parameters

- `q`: Keyword search against title, description, or farm name.
- `category`: Filter by category slug (e.g., `vegetables`, `grains`, `fruits`, `dairy-poultry`).
- `max_price`: Numeric upper price bound.
- `location`: Location name (e.g., `Nakuru`).
- `sort`: Ordering (`latest`, `price_asc`, `price_desc`).
- `page`: Page number.

### Sample Response

```json
{
  "ok": true,
  "items": [
    {
      "id": 1,
      "title": "Fresh Tomatoes",
      "category": "Vegetables",
      "price": 120.0,
      "unit": "kg",
      "location": "Kiambu",
      "farm_name": "Kiambu Fresh Farm",
      "image_url": "https://...",
      "in_stock": true,
      "url": "product.php?id=1"
    }
  ],
  "total": 1,
  "page": 1,
  "pages": 1
}
```

---

## Business Rules

- **Single Table User System:** Roles (`buyer`, `farmer`, `admin`) are specified inside the single `users` table.
- **Database Price Verification:** Session cart maintains product IDs and quantities only. Item prices are re-fetched during display and re-verified in transactional locks at checkout.
- **Flat Delivery Fee:** Every non-empty order incurs the `DELIVERY_FEE` set in config.
- **Concurrency Locking:** Database rows are locked (`SELECT ... FOR UPDATE`) inside transactions at checkout to avoid over-selling.
- **Order Cancellation:** Cancelling an order via the admin panel automatically increments the stock back to the listing.
- **Receipt Integrity:** Line items preserve item price, title, and unit at the moment of checkout, ensuring historic accuracy if the listing changes later.

---

## Security

- Passwords managed using native `password_hash()` and `password_verify()`.
- CSRF protections enforced on state-changing forms. `logout.php` only accepts `POST` calls with a valid CSRF token.
- Queries executed via PDO prepared statements.
- Output values sanitized through HTML escaping functions.
- Sessions regenerated post-login with `HttpOnly`, `SameSite=Lax`, and `Secure` attributes enabled where appropriate.
- Rate limiting protects logins (5 failed attempts per session window per 5 minutes).

---

## Production Checklist

- [ ] Set a strong `ADMIN_SETUP_KEY`, configure administrator, then **delete `setup_admin.php`**.
- [ ] Configure a non-root MySQL user with explicit privileges.
- [ ] Ensure `seed.php` is deleted or removed from production environments.
- [ ] Enforce SSL/HTTPS connections.
- [ ] Set `display_errors = Off` in your PHP configuration.
- [ ] Restrict public web access to `database/` and `docs/` directories.

---

## Troubleshooting

| Issue                            | Solution                                                               |
| :------------------------------- | :--------------------------------------------------------------------- |
| **"Database connection failed"** | Verify `DB_*` config parameters and check if MySQL service is running. |
| **`could not find driver`**      | Enable `pdo_mysql` inside your `php.ini` file.                         |
| **Empty Catalogue**              | Run `php database/seed.php` or populate items via a Farmer account.    |
| **Page Expired (419)**           | Refresh the page to obtain a fresh CSRF token.                         |

---

## Documentation

- [`docs/WIREFRAMES.md`](docs/WIREFRAMES.md) – Screen layouts and user flows.
- [`docs/ERD.md`](docs/ERD.md) – Database ER diagram and schema descriptions.

---

&copy; AgriMarket. Connecting smallholder farmers to buyers.
