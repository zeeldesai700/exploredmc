
-- Tour & Travel Quotation App - MySQL schema

CREATE DATABASE IF NOT EXISTS tour_quotation_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tour_quotation_app;

-- Users (admin, employee)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','employee') NOT NULL DEFAULT 'employee',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Customers
CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(30),
  email VARCHAR(255),
  address TEXT,
  city VARCHAR(100),
  state VARCHAR(100),
  country VARCHAR(100) DEFAULT 'India',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Quotations
CREATE TABLE IF NOT EXISTS quotations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quotation_no VARCHAR(50) NOT NULL UNIQUE,
  customer_id INT NOT NULL,
  created_by INT NOT NULL,
  status ENUM('draft','sent','accepted','rejected') NOT NULL DEFAULT 'draft',
  currency VARCHAR(10) NOT NULL DEFAULT 'INR',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tax_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  notes TEXT,
  valid_until DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Quotation line items
CREATE TABLE IF NOT EXISTS quotation_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT NOT NULL,
  line_no INT NOT NULL,
  service_type ENUM('hotel','car','sightseeing','other') NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  qty DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  unit VARCHAR(20) DEFAULT 'unit',
  price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- total for the line incl. tax
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (quotation_id) REFERENCES quotations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Optional: first admin seeder helper table (not required)

-- Indexes
CREATE INDEX idx_q_customer ON quotations(customer_id);
CREATE INDEX idx_q_created ON quotations(created_at);
