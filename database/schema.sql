CREATE DATABASE online_auction;
USE online_auction;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    role ENUM('buyer','seller') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE auctions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT,
    title VARCHAR(255),
    description TEXT,
    start_price DECIMAL(10,2),
    current_price DECIMAL(10,2),
    end_time DATETIME,
    image VARCHAR(255),
    status ENUM('active','closed') DEFAULT 'active',
    FOREIGN KEY (seller_id) REFERENCES users(id)
);

CREATE TABLE bids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    auction_id INT,
    bidder_id INT,
    bid_amount DECIMAL(10,2),
    bid_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (auction_id) REFERENCES auctions(id),
    FOREIGN KEY (bidder_id) REFERENCES users(id)
);
ALTER TABLE auctions ADD image VARCHAR(255);
