-- Multi-Business POS & Invoice System Database Schema
-- UAE VAT Compliant System

-- Create database
CREATE DATABASE IF NOT EXISTS uae_pos_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uae_pos_system;

-- Businesses table
CREATE TABLE businesses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(255) NOT NULL,
    business_name_arabic VARCHAR(255),
    trn_number VARCHAR(50) UNIQUE NOT NULL,
    address TEXT,
    address_arabic TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'UAE',
    phone VARCHAR(20),
    email VARCHAR(255),
    website VARCHAR(255),
    logo_path VARCHAR(500),
    signature_path VARCHAR(500),
    vat_rate DECIMAL(5,2) DEFAULT 5.00,
    currency VARCHAR(3) DEFAULT 'AED',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    product_code VARCHAR(100),
    product_name VARCHAR(255) NOT NULL,
    product_name_arabic VARCHAR(255),
    description TEXT,
    description_arabic TEXT,
    category VARCHAR(100),
    unit VARCHAR(50) DEFAULT 'pcs',
    price DECIMAL(10,2) NOT NULL,
    cost DECIMAL(10,2),
    stock_quantity INT DEFAULT 0,
    min_stock_level INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_id (business_id),
    INDEX idx_product_code (product_code),
    INDEX idx_category (category)
);

-- Customers table
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_name_arabic VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(20),
    address TEXT,
    address_arabic TEXT,
    trn_number VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_id (business_id),
    INDEX idx_email (email),
    INDEX idx_phone (phone)
);

-- Invoices table
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    invoice_number VARCHAR(100) NOT NULL,
    customer_id INT,
    invoice_date DATE NOT NULL,
    due_date DATE,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    payment_method ENUM('cash', 'card', 'bank_transfer', 'cheque', 'other') DEFAULT 'cash',
    payment_status ENUM('pending', 'partial', 'paid') DEFAULT 'pending',
    notes TEXT,
    notes_arabic TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    UNIQUE KEY unique_invoice_number (business_id, invoice_number),
    INDEX idx_business_id (business_id),
    INDEX idx_customer_id (customer_id),
    INDEX idx_invoice_date (invoice_date),
    INDEX idx_status (status)
);

-- Invoice items table
CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_id INT,
    item_name VARCHAR(255) NOT NULL,
    item_name_arabic VARCHAR(255),
    description TEXT,
    description_arabic TEXT,
    quantity DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    unit_price DECIMAL(10,2) NOT NULL,
    vat_rate DECIMAL(5,2) DEFAULT 5.00,
    vat_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_product_id (product_id)
);

-- POS transactions table
CREATE TABLE pos_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    transaction_number VARCHAR(100) NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    vat_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cash', 'card', 'bank_transfer', 'cheque', 'other') DEFAULT 'cash',
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    change_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cashier_name VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_transaction_number (business_id, transaction_number),
    INDEX idx_business_id (business_id),
    INDEX idx_transaction_date (transaction_date)
);

-- POS transaction items table
CREATE TABLE pos_transaction_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    product_id INT,
    item_name VARCHAR(255) NOT NULL,
    quantity DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    unit_price DECIMAL(10,2) NOT NULL,
    vat_rate DECIMAL(5,2) DEFAULT 5.00,
    vat_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES pos_transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_product_id (product_id)
);

-- Settings table for system configuration
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json') DEFAULT 'text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_business_setting (business_id, setting_key),
    INDEX idx_business_id (business_id)
);

-- Insert default business (you can modify this)
INSERT INTO businesses (
    business_name, 
    business_name_arabic, 
    trn_number, 
    address, 
    address_arabic, 
    city, 
    phone, 
    email, 
    vat_rate
) VALUES (
    'Sample Business LLC',
    'شركة عينة ذ.م.م',
    '100123456789003',
    '123 Business Street, Downtown',
    'شارع الأعمال 123، وسط البلد',
    'Dubai',
    '+971 4 123 4567',
    'info@samplebusiness.ae',
    5.00
);

-- Insert some sample products
INSERT INTO products (
    business_id, 
    product_code, 
    product_name, 
    product_name_arabic, 
    description, 
    category, 
    price, 
    stock_quantity
) VALUES 
(1, 'PROD001', 'Office Chair', 'كرسي مكتب', 'Ergonomic office chair', 'Furniture', 299.00, 50),
(1, 'PROD002', 'Laptop', 'حاسوب محمول', 'Business laptop 15 inch', 'Electronics', 2500.00, 25),
(1, 'PROD003', 'Coffee Mug', 'كوب قهوة', 'Ceramic coffee mug', 'Office Supplies', 15.00, 100),
(1, 'PROD004', 'Notebook', 'دفتر ملاحظات', 'A4 lined notebook', 'Office Supplies', 8.50, 200),
(1, 'PROD005', 'Desk Lamp', 'مصباح مكتب', 'LED desk lamp', 'Electronics', 89.00, 30);

-- Insert sample customer
INSERT INTO customers (
    business_id, 
    customer_name, 
    customer_name_arabic, 
    email, 
    phone, 
    address, 
    trn_number
) VALUES (
    1, 
    'ABC Trading LLC', 
    'شركة أي بي سي للتجارة ذ.م.م', 
    'contact@abctrading.ae', 
    '+971 4 987 6543', 
    '456 Trade Center, Business Bay', 
    '200987654321003'
);