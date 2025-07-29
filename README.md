# Online Auction System

A comprehensive web-based platform for conducting online auctions, allowing users to list items for sale, browse and bid on auctions, manage profiles, communicate, and access administrative tools.

---

## 📑 Table of Contents

- [Features Implemented](#features-implemented)
  - [Core Auction Functionality](#core-auction-functionality)
  - [User Management & Experience](#user-management--experience)
  - [Messaging System](#messaging-system)
  - [Feedback & Rating System](#feedback--rating-system)
  - [Administration & Analytics](#administration--analytics)
  - [Payment Simulation](#payment-simulation)
- [Project Structure](#project-structure)
- [Technologies Used](#technologies-used)
- [Setup Instructions](#setup-instructions)
- [Future Upgrades](#future-upgrades)
- [Contributing](#contributing)
- [License](#license)

---

## ✅ Features Implemented

We have successfully implemented the core functionalities and a significant portion of user experience and admin tools.

### 🔁 Core Auction Functionality

- **Item Listing**: Sellers can list items with title, description, prices, and auction timings.
- **Browse Auctions**: With search, filters (category, price), and sorting (by price, time, title).
- **Item Detail Page**: Shows full details, bid history, real-time countdown, and "Buy Now" option.
- **Bidding Mechanism**:
  - Auto bid increments.
  - Proxy/Max bidding.
  - Anti-sniping logic.
- **Buy Now**: Immediate purchase to end auction.
- **Automated Auction Closing** via `cron_jobs/close_auctions.php`.

### 👤 User Management & Experience

- **Authentication**: Secure registration/login with session management.
- **User Dashboard**:
  - Active listings, current bids, won items, feedback opportunities.
- **Profile Management**: Edit profile, upload profile picture.
- **Watchlist**: Track favorite items.
- **Notifications**: Real-time alerts for bid status, auction changes, messages, and payments.

### 💬 Messaging System

- Inbox and thread view.
- Compose new messages or reply.
- Contextual messaging from item pages.
- Unread message indicators.

### ⭐ Feedback & Rating System

- Leave star ratings and comments post-transaction.
- Dashboard highlights pending feedback.

### ⚙️ Administration & Analytics

- **Admin Dashboard**
- **User Management (CRUD)**
- **Item Management (CRUD)**
- **Category Management**
- **Basic Analytics**: Revenue, top sellers, top bidders, item stats.

### 💳 Payment Simulation

- Simulated payment using a keyword (`PAYMENT`).
- Status updates and related notifications.
- Delayed processing imitation.

---

## 📁 Project Structure

```plaintext
OnlineAuctionSystem/
├── assets/
│   ├── css/style.css
│   ├── js/script.js
│   └── images/profile_pictures/
├── config/db.php
├── includes/auth_functions.php
├── public/
│   ├── index.php
│   ├── register.php, login.php, dashboard.php
│   ├── add_item.php, browse_auctions.php, item_detail.php
│   ├── profile.php, watchlist.php, notifications.php
│   ├── messages.php, view_message.php, leave_feedback.php
│   ├── process_payment.php
│   └── admin/
│       ├── index.php, manage_users.php, manage_items.php
│       ├── manage_categories.php, reports.php
├── cron_jobs/close_auctions.php
├── node_modules/
├── package.json, package-lock.json
├── tailwind.config.js, postcss.config.js
├── database.sql
└── .gitignore
````

---

## 🛠 Technologies Used

### Backend

* PHP
* MySQL
* PDO

### Frontend

* HTML5
* Tailwind CSS
* JavaScript

### Development Tools

* XAMPP / LAMP / WAMP
* Node.js + NPM (for Tailwind CSS)
* phpMyAdmin

---

## ⚙️ Setup Instructions

### 1. Clone or Download the Project

```bash
git clone <repository-url>
cd OnlineAuctionSystem
```

Or download and place in `htdocs` (XAMPP).

### 2. Start Local Server

* Place folder in `C:\xampp\htdocs\`
* Start Apache and MySQL in XAMPP

### 3. Setup Database

* Go to `http://localhost/phpmyadmin`
* Create DB: `online_auction_db`
* Import `database.sql` file

> ⚠️ Only run CREATE TABLE statements if DB already has data.

### 4. Configure Database Connection

* Edit `config/db.php` with your DB credentials

### 5. Install Tailwind Dependencies

```bash
npm install -D tailwindcss postcss autoprefixer
npx tailwindcss init -p
```

### 6. Build CSS

```bash
npm run build-css
# Or watch for changes:
npm run watch-css
```

Ensure your `package.json` has:

```json
"scripts": {
  "build-css": "tailwindcss build assets/css/tailwind.css -o assets/css/style.css",
  "watch-css": "tailwindcss build assets/css/tailwind.css -o assets/css/style.css --watch"
}
```

### 7. Create Profile Picture Folder

```bash
mkdir -p assets/images/profile_pictures
# Set write permission if needed:
chmod 777 assets/images/profile_pictures
```

### 8. Set up Cron Job (Auction Closing)

#### Linux/macOS:

```bash
crontab -e
* * * * * /usr/bin/php /path/to/OnlineAuctionSystem/cron_jobs/close_auctions.php >> /var/log/auction_cron.log 2>&1
```

#### Windows:

Use **Task Scheduler** to run `php.exe` with path to `close_auctions.php` every 1–5 mins.

### 9. Launch App

Open in browser:

```
http://localhost/OnlineAuctionSystem/public/
```

---

## 🚀 Future Upgrades

### I. Core Enhancements

* **Real-time bidding** with WebSockets

### II. Payments & Security

* Integration with **Stripe**, **PayPal**, etc.
* Add **2FA**, **CAPTCHA**, **email verification**
* Monitor suspicious activities

### III. Admin Tools

* Bulk actions, audit logs, user banning
* Manual auction cancellation & refunding

### IV. Dispute Resolution & Recommendations

* Dedicated system for raising issues
* Recommendation engine based on activity

### V. Legal Compliance

* ToS, Privacy Policy, Cookie Consent
* Age Verification for sensitive listings

---

## 🤝 Contributing

Contributions are welcome! Feel free to fork, submit issues, and create pull requests.

---

## 📄 License

This project is licensed under the **MIT License**.

