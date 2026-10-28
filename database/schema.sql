-- AgriMarket database schema (MySQL 8 / MariaDB 10.4+)
-- Import with:  mysql -u root -p < database/schema.sql

CREATE DATABASE IF NOT EXISTS agrimarket
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE agrimarket;

-- ---------------------------------------------------------------
-- Users (buyers, farmers and administrators share one table)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  phone         VARCHAR(20)  NOT NULL,
  role          ENUM('buyer','farmer','admin') NOT NULL DEFAULT 'buyer',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Farmer profiles (1:1 with users whose role = 'farmer')
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS farmers (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  farm_name  VARCHAR(120) NOT NULL,
  location   VARCHAR(80)  NOT NULL,
  bio        TEXT NULL,
  verified   TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_farmers_user (user_id),
  CONSTRAINT fk_farmers_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Product categories
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  slug VARCHAR(60) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB;

INSERT IGNORE INTO categories (name, slug) VALUES
  ('Vegetables',      'vegetables'),
  ('Grains',          'grains'),
  ('Fruits',          'fruits'),
  ('Dairy & Poultry', 'dairy-poultry');

-- ---------------------------------------------------------------
-- Produce listings
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  farmer_id    INT UNSIGNED NOT NULL,
  category_id  INT UNSIGNED NOT NULL,
  title        VARCHAR(150) NOT NULL,
  description  TEXT NOT NULL,
  price        DECIMAL(10,2) NOT NULL,
  unit         VARCHAR(40)  NOT NULL,
  stock        INT UNSIGNED NOT NULL DEFAULT 0,
  location     VARCHAR(80)  NOT NULL,
  image_url    VARCHAR(500) NULL,
  harvest_date DATE NULL,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_products_farmer (farmer_id),
  KEY idx_products_category (category_id),
  KEY idx_products_listing (is_active, created_at),
  KEY idx_products_location (location),
  CONSTRAINT fk_products_farmer FOREIGN KEY (farmer_id)
    REFERENCES farmers (id) ON DELETE CASCADE,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id)
    REFERENCES categories (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Orders and their line items
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED NOT NULL,
  subtotal       DECIMAL(10,2) NOT NULL,
  delivery_fee   DECIMAL(10,2) NOT NULL,
  total          DECIMAL(10,2) NOT NULL,
  status         ENUM('pending','confirmed','delivered','cancelled') NOT NULL DEFAULT 'pending',
  payment_method ENUM('mpesa','cod') NOT NULL DEFAULT 'mpesa',
  payment_status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
  phone          VARCHAR(20)  NOT NULL,
  address        VARCHAR(255) NOT NULL,
  notes          VARCHAR(500) NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_orders_user (user_id, created_at),
  KEY idx_orders_status (status),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Title, unit and price are copied onto the line so receipts stay accurate
-- even if the farmer later edits or removes the listing.
CREATE TABLE IF NOT EXISTS order_items (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id   INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  farmer_id  INT UNSIGNED NOT NULL,
  title      VARCHAR(150) NOT NULL,
  unit       VARCHAR(40)  NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity   INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_items_order (order_id),
  KEY idx_items_farmer (farmer_id),
  CONSTRAINT fk_items_order FOREIGN KEY (order_id)
    REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id)
    REFERENCES products (id) ON DELETE SET NULL,
  CONSTRAINT fk_items_farmer FOREIGN KEY (farmer_id)
    REFERENCES farmers (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Contact form submissions
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL,
  subject    VARCHAR(150) NOT NULL,
  message    TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;
