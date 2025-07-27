-- database.sql - SQL script to create the initial schema for the Online Auction System

-- Drop database if it exists to ensure a clean slate for development
DROP DATABASE IF EXISTS online_auction_db;

-- Create the database
CREATE DATABASE online_auction_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the newly created database
USE online_auction_db;

-- 1. Users Table
-- Stores user information, including authentication details and profile data.
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL, -- Stores hashed password
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    profile_picture VARCHAR(255) DEFAULT NULL, -- URL or path to profile picture
    contact_info VARCHAR(255) DEFAULT NULL, -- e.g., phone number, address
    is_admin BOOLEAN DEFAULT FALSE, -- Flag for administrator accounts
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Categories Table
-- Stores different categories for auction items (e.g., Electronics, Antiques, Books).
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT
);

-- 3. Items Table
-- Stores details about each item listed for auction.
CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category_id INT NOT NULL,
    seller_id INT NOT NULL,
    start_price DECIMAL(10, 2) NOT NULL,
    reserve_price DECIMAL(10, 2) DEFAULT NULL, -- Hidden minimum price seller is willing to accept
    buy_now_price DECIMAL(10, 2) DEFAULT NULL, -- Option to buy immediately
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    current_bid DECIMAL(10, 2) DEFAULT NULL, -- Current highest bid
    highest_bidder_id INT DEFAULT NULL, -- ID of the current highest bidder
    status ENUM('active', 'closed', 'pending', 'sold', 'cancelled') DEFAULT 'pending',
    image_urls JSON DEFAULT NULL, -- Stores JSON array of image URLs/paths
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (highest_bidder_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 4. Bids Table
-- Records all bids placed on items.
CREATE TABLE bids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    bidder_id INT NOT NULL,
    bid_amount DECIMAL(10, 2) NOT NULL,
    bid_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_proxy_bid BOOLEAN DEFAULT FALSE, -- True if this was a proxy bid
    proxy_max_amount DECIMAL(10, 2) DEFAULT NULL, -- The maximum amount set by the proxy bidder
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (bidder_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. Sold Items Table
-- Records details of items that have been successfully sold through auction.
CREATE TABLE sold_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL UNIQUE, -- Ensures one entry per sold item
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    final_price DECIMAL(10, 2) NOT NULL,
    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    shipping_status ENUM('pending', 'shipped', 'delivered') DEFAULT 'pending',
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. Messages Table
-- For direct communication between users (e.g., buyer-seller inquiries).
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    item_id INT DEFAULT NULL, -- Optional: context to which item the message relates
    subject VARCHAR(255) DEFAULT NULL,
    message_text TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_read BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE SET NULL
);

-- 7. Feedback Profiles Table
-- Stores feedback ratings and comments between buyers and sellers.
CREATE TABLE feedback_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- The user receiving the feedback
    rater_id INT NOT NULL, -- The user giving the feedback
    item_id INT NOT NULL, -- The item related to the transaction
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5), -- Rating from 1 to 5
    comment TEXT,
    feedback_type ENUM('buyer_to_seller', 'seller_to_buyer') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- Optional: Add some initial data for testing (e.g., categories)
INSERT INTO categories (name, description) VALUES
('Electronics', 'Gadgets, devices, and electronic components.'),
('Antiques', 'Old and valuable items, collectibles.'),
('Books', 'Fiction, non-fiction, and rare books.'),
('Home & Garden', 'Items for home decor, furniture, and gardening.'),
('Fashion', 'Apparel, accessories, and jewelry.');

-- You would typically add test users and items here as well for development.
-- Note: Ensure to hash passwords before inserting user data in a real application.