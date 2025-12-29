# Online Auction System

A full-featured web-based auction platform that enables users to list items, place bids, manage auctions, communicate securely, and perform administrative operations. The system is designed with scalability, usability, and extensibility in mind, making it suitable for academic projects and real-world learning.

---

## Table of Contents

* [Overview](#overview)
* [Features](#features)

  * [Core Auction Functionality](#core-auction-functionality)
  * [User Management & Experience](#user-management--experience)
  * [Messaging System](#messaging-system)
  * [Feedback & Rating System](#feedback--rating-system)
  * [Administration & Analytics](#administration--analytics)
  * [Payment Simulation](#payment-simulation)
* [Project Structure](#project-structure)
* [Technologies Used](#technologies-used)
* [Setup Instructions](#setup-instructions)
* [Future Enhancements](#future-enhancements)
* [Contributing](#contributing)
* [License](#license)

---

## Overview

The **Online Auction System** provides a complete auction workflow, from item listing and bidding to auction closing, payments, feedback, and administration. It includes advanced auction mechanics such as proxy bidding, anti-sniping, notifications, and automated auction closing using background jobs.

---

## Features

### Core Auction Functionality

* **Item Listing**

  * Create auction items with title, description, category, and images
  * Starting price, optional reserve price, optional Buy Now price
  * Configurable auction end time

* **Browse Auctions**

  * Keyword-based search (title and description)
  * Filters by category and price range
  * Sorting options:

    * Ending soonest / latest
    * Price (low to high / high to low)
    * Title (A-Z / Z-A)

* **Item Detail Page**

  * Complete item information
  * Current highest bid and bidder
  * Real-time countdown timer
  * Full bid history

* **Bidding System**

  * Manual bidding on active auctions
  * Automatic bid increments based on current bid
  * Proxy (max) bidding support
  * Anti-sniping protection (automatic time extension)
  * Buy Now option to instantly close auctions

* **Automated Auction Closing**

  * Background script to:

    * Detect expired auctions
    * Determine winners and reserve price fulfillment
    * Update auction status (sold or closed)
    * Record completed sales
    * Send notifications to buyers, sellers, and bidders

---

### User Management & Experience

* Secure user registration and login (password hashing and session management)

* User dashboard displaying:

  * Active auction listings
  * Ongoing bids with highest or outbid status
  * Won items with payment status
  * Pending feedback opportunities

* User profile management:

  * Personal information updates
  * Profile picture upload and management

* Watchlist to track favorite items

* In-app notification system for:

  * Outbid alerts
  * Auction wins
  * Item sold confirmations
  * Auction time extensions
  * New messages
  * Payment confirmations

---

### Messaging System

* User inbox with all conversations
* Thread-based message view
* Compose and reply to messages
* Context-aware messaging from item detail pages
* Unread message indicators

---

### Feedback & Rating System

* Post-transaction feedback system
* Star ratings (1 to 5) with written comments
* Separate feedback for buyers and sellers
* Dashboard reminders for pending feedback

---

### Administration & Analytics

* Secure admin-only dashboard

* **User Management**

  * View, edit, and delete users
  * Admin role assignment
  * Safeguards against self-deletion

* **Item Management**

  * View and edit auction items
  * Delete items with cascading cleanup of related data

* **Category Management**

  * Full CRUD support
  * Validation to prevent deletion of active categories

* **Analytics & Reports**

  * Total and new user counts
  * Active, closed, and sold auctions
  * Total revenue from completed sales
  * Top categories by item count
  * Top sellers by listings
  * Top bidders by bid volume

---

### Payment Simulation

* Dummy payment gateway for testing purposes
* Keyword-based payment confirmation
* Simulated processing delay
* Payment status tracking
* Buyer and seller notifications

---

## Project Structure

```
OnlineAuctionSystem/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/profile_pictures/
├── config/
│   └── db.php
├── includes/
│   └── auth_functions.php
├── public/
│   ├── index.php
│   ├── register.php
│   ├── login.php
│   ├── dashboard.php
│   ├── add_item.php
│   ├── browse_auctions.php
│   ├── item_detail.php
│   ├── profile.php
│   ├── watchlist.php
│   ├── notifications.php
│   ├── messages.php
│   ├── view_message.php
│   ├── leave_feedback.php
│   ├── process_payment.php
│   └── admin/
│       ├── index.php
│       ├── manage_users.php
│       ├── manage_items.php
│       ├── manage_categories.php
│       └── reports.php
├── cron_jobs/
│   └── close_auctions.php
├── node_modules/
├── database.sql
├── package.json
├── tailwind.config.js
├── postcss.config.js
└── .gitignore
```

---

## Technologies Used

### Backend

* PHP
* MySQL
* PDO

### Frontend

* HTML5
* Tailwind CSS
* JavaScript

### Tools & Environment

* XAMPP (Apache, MySQL, PHP)
* Node.js and npm
* Tailwind CSS build pipeline

---

## Setup Instructions

1. **Clone or Download the Project**

   ```bash
   git clone <repository-url>
   cd OnlineAuctionSystem
   ```

2. **Server Setup**

   * Install XAMPP
   * Place the project inside `htdocs`
   * Start Apache and MySQL services

3. **Database Setup**

   * Create a database named `online_auction_db`
   * Import `database.sql`
   * Update database credentials in `config/db.php`

4. **Install Frontend Dependencies**

   ```bash
   npm install
   npm run build-css
   ```

5. **Create Upload Directory**

   ```
   assets/images/profile_pictures/
   ```

   Ensure the directory has write permissions.

6. **Configure Cron Job**

   * Schedule `cron_jobs/close_auctions.php` to run every 1 to 5 minutes

7. **Run the Application**

   ```
   http://localhost/OnlineAuctionSystem/public/
   ```

---

## Future Enhancements

* Real-time bidding using WebSockets
* Live payment gateway integration (Stripe, PayPal)
* Two-factor authentication
* Email verification
* CAPTCHA protection
* Advanced admin controls (audit logs, bans, bulk actions)
* Dispute resolution system
* Recommendation engine
* Legal and compliance features (ToS, Privacy Policy, cookie consent)

---

## Contributing

Contributions are welcome. Fork the repository, create a feature branch, and submit a pull request with proper documentation.

---

## License

This project is licensed under the **MIT License**.
