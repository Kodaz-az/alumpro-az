-- Alumpro.Az Database Structure

CREATE DATABASE alumpro_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE alumpro_system;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('admin', 'sales', 'customer') NOT NULL,
    store_id INT,
    profile_image VARCHAR(255),
    is_verified BOOLEAN DEFAULT FALSE,
    verification_code VARCHAR(6),
    warehouse_access BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Stores table
CREATE TABLE stores (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    manager_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    type ENUM('profile', 'accessory', 'glass') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table (Aluminum Profiles)
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    category_id INT,
    type VARCHAR(50),
    color VARCHAR(50),
    unit VARCHAR(20),
    size VARCHAR(50),
    quantity INT DEFAULT 0,
    purchase_price DECIMAL(10,2),
    selling_price DECIMAL(10,2),
    sold_quantity INT DEFAULT 0,
    remaining_quantity INT DEFAULT 0,
    date_added DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Glass table
CREATE TABLE glass (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50),
    color VARCHAR(50),
    height DECIMAL(8,2),
    width DECIMAL(8,2),
    quantity INT DEFAULT 0,
    total_area DECIMAL(10,2),
    purchase_price DECIMAL(10,2),
    selling_price DECIMAL(10,2),
    sold_quantity INT DEFAULT 0,
    sold_area DECIMAL(10,2) DEFAULT 0,
    remaining_quantity INT DEFAULT 0,
    remaining_area DECIMAL(10,2) DEFAULT 0,
    date_added DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Accessories table
CREATE TABLE accessories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    category_id INT,
    color VARCHAR(50),
    unit VARCHAR(20),
    length DECIMAL(8,2),
    quantity INT DEFAULT 0,
    purchase_price DECIMAL(10,2),
    selling_price DECIMAL(10,2),
    sold_quantity INT DEFAULT 0,
    remaining_quantity INT DEFAULT 0,
    date_added DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Customers table
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    company_name VARCHAR(100),
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    notes TEXT,
    total_orders INT DEFAULT 0,
    total_spent DECIMAL(12,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT,
    sales_person_id INT,
    store_id INT,
    order_date DATE,
    subtotal DECIMAL(12,2),
    discount DECIMAL(10,2) DEFAULT 0,
    transport_cost DECIMAL(10,2) DEFAULT 0,
    assembly_cost DECIMAL(10,2) DEFAULT 0,
    accessories_cost DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(12,2),
    status ENUM('pending', 'in_production', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    barcode VARCHAR(100),
    production_worker VARCHAR(100),
    delivered_by VARCHAR(100),
    received_by VARCHAR(100),
    delivery_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (sales_person_id) REFERENCES users(id),
    FOREIGN KEY (store_id) REFERENCES stores(id)
);

-- Order items table
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT,
    item_type ENUM('door', 'glass', 'accessory') NOT NULL,
    profile_type VARCHAR(50),
    glass_type VARCHAR(50),
    accessory_id INT,
    height DECIMAL(8,2),
    width DECIMAL(8,2),
    quantity INT,
    unit_price DECIMAL(10,2),
    total_price DECIMAL(10,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (accessory_id) REFERENCES accessories(id)
);

-- Support keywords table
CREATE TABLE support_keywords (
    id INT PRIMARY KEY AUTO_INCREMENT,
    keyword VARCHAR(255) NOT NULL,
    response TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Support conversations table
CREATE TABLE support_conversations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id VARCHAR(100),
    user_id INT,
    message TEXT,
    response TEXT,
    is_from_user BOOLEAN DEFAULT TRUE,
    sales_person_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (sales_person_id) REFERENCES users(id)
);

-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    title VARCHAR(255),
    message TEXT,
    type ENUM('order', 'general', 'discount', 'news'),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Settings table
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- News table
CREATE TABLE news (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    image VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Gallery table
CREATE TABLE gallery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255),
    image VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User sessions table
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    session_token VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert default data
INSERT INTO categories (name, type) VALUES
('Aluminium Profil', 'profile'),
('Qapı Aksesuarları', 'accessory'),
('Şüşə', 'glass'),
('Bərkitmə Elementləri', 'accessory');

INSERT INTO stores (name, address, phone) VALUES
('Mağaza 1', 'Bakı şəhəri, Nəsimi rayonu', '+994501234567'),
('Mağaza 2', 'Bakı şəhəri, Yasamal rayonu', '+994501234568');

INSERT INTO settings (setting_key, setting_value, description) VALUES
('company_name', 'Alumpro.Az', 'Şirkət adı'),
('company_phone', '+994501234567', 'Şirkət telefonu'),
('company_email', 'info@alumpro.az', 'Şirkət email'),
('company_address', 'Bakı, Azərbaycan', 'Şirkət ünvanı'),
('whatsapp_api_url', '', 'WhatsApp API URL'),
('onesignal_app_id', '', 'OneSignal App ID'),
('onesignal_api_key', '', 'OneSignal API Key'),
('glass_size_reduction', '4', 'Şüşə ölçü azalması (mm)'),
('order_number_prefix', 'ALM', 'Sifariş nömrəsi prefiksi');

INSERT INTO support_keywords (keyword, response) VALUES
('salam', 'Salam! Alumpro.Az-a xoş gəlmisiniz. Sizə necə kömək edə bilərəm?'),
('qiymət', 'Qiymətlər haqqında məlumat üçün satış menecerimirlə əlaqə saxlayın. Tel: +994501234567'),
('sifariş', 'Sifariş vermək üçün qeydiyyatdan keçin və ya bizimlə əlaqə saxlayın.'),
('çatdırılma', 'Çatdırılma xidməti mövcuddur. Ətraflı məlumat üçün bizimlə əlaqə saxlayın.'),
('kömək', 'Sizə kömək etməkdən məmnunuq. Sualınızı dəqiqləşdirin.');