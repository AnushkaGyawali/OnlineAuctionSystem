-- database.sql - SQL script to create the initial schema for the Online Auction System

-- Drop database if it exists to ensure a clean slate for development
-- CAUTION: Running this will delete all existing data!
-- DROP DATABASE IF EXISTS online_auction_db;

-- Create the database (only if it doesn't exist, or if you dropped it)
-- CREATE DATABASE online_auction_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the existing database
USE online_auction_db;

-- 1. Users Table (No changes, but including for context)
-- Stores user information, including authentication details and profile data.
-- Ensure 'profile_picture' column exists as VARCHAR(255)
-- ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL; -- Run this if you don't have it

-- 2. Categories Table (No changes)
-- Stores different categories for auction items (e.g., Electronics, Antiques, Books).

-- 3. Items Table (No changes)
-- Stores details about each item listed for auction.

-- 4. Bids Table (No changes)
-- Records all bids placed on items.

-- 5. Sold Items Table (No changes)
-- Records details of items that have been successfully sold through auction.

-- 6. Messages Table (No changes)
-- For direct communication between users (e.g., buyer-seller inquiries).

-- 7. Feedback Profiles Table (No changes)
-- Stores feedback ratings and comments between buyers and sellers.

-- NEW TABLE: 8. Watchlists Table
-- Allows users to save items they are interested in.
CREATE TABLE IF NOT EXISTS watchlists (
    user_id INT NOT NULL,
    item_id INT NOT NULL,
    watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, item_id), -- Composite primary key to prevent duplicate watches
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- NEW TABLE: 9. Notifications Table
-- Stores various notifications for users (e.g., outbid, auction won, new message).
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL, -- e.g., 'bid_outbid', 'auction_won', 'new_message', 'system_alert'
    message TEXT NOT NULL,
    link VARCHAR(255) DEFAULT NULL, -- Optional link to relevant page (e.g., item_detail.php?id=X)
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Optional: Add some initial data for testing (e.g., categories)
-- Only run these INSERTS if your tables are empty or you dropped and recreated them.
-- INSERT INTO categories (name, description) VALUES
-- ('Electronics', 'Gadgets, devices, and electronic components.'),
-- ('Antiques', 'Old and valuable items, collectibles.'),
-- ('Books', 'Fiction, non-fiction, and rare books.'),
-- ('Home & Garden', 'Items for home decor, furniture, and gardening.'),
-- ('Fashion', 'Apparel, accessories, and jewelry.');
