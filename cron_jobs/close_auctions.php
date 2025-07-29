<?php
// cron_jobs/close_auctions.php - Script to automatically close ended auctions

// This script is intended to be run via a cron job or similar task scheduler,
// NOT directly via a web browser.

// Prevent direct web access (optional, but good practice for cron scripts)
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

// Include database connection
// Note: Path is relative to this script, not the web root
require_once __DIR__ . '/../config/db.php';

echo "Starting auction closing process...\n";

try {
    // 1. Find auctions that have ended and are still 'active'
    $stmt_ended_auctions = $pdo->prepare("
        SELECT
            id,
            title, -- Added title for notification messages
            seller_id,
            highest_bidder_id,
            current_bid,
            reserve_price,
            buy_now_price,
            status
        FROM
            items
        WHERE
            end_time <= NOW() AND status = 'active'
    ");
    $stmt_ended_auctions->execute();
    $ended_auctions = $stmt_ended_auctions->fetchAll();

    if (empty($ended_auctions)) {
        echo "No auctions to close at this time.\n";
    } else {
        echo "Found " . count($ended_auctions) . " ended auctions to process.\n";

        foreach ($ended_auctions as $auction) {
            $pdo->beginTransaction(); // Start transaction for each auction

            $item_id = $auction['id'];
            $item_title = $auction['title'];
            $seller_id = $auction['seller_id'];
            $highest_bidder_id = $auction['highest_bidder_id'];
            $current_bid = $auction['current_bid'];
            $reserve_price = $auction['reserve_price'];
            // $buy_now_price = $auction['buy_now_price']; // Not directly used for closing, but good to know context

            $new_status = 'closed'; // Default to closed (unsold)
            $final_price = 0.00;
            $buyer_id = NULL;

            // Prepare notification statement
            $stmt_insert_notification = $pdo->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");

            // Determine if the item was sold
            if ($highest_bidder_id !== NULL && $current_bid !== NULL) {
                // If there's a highest bidder and a current bid
                if ($reserve_price === NULL || $current_bid >= $reserve_price) {
                    // Reserve price met or no reserve price
                    $new_status = 'sold';
                    $final_price = $current_bid;
                    $buyer_id = $highest_bidder_id;
                    echo "  Item #{$item_id}: Sold to bidder #{$buyer_id} for $" . number_format($final_price, 2) . ".\n";

                    // Notify winner (buyer)
                    $winner_message = "Congratulations! You won the auction for '" . $item_title . "' for $" . number_format($final_price, 2) . ".";
                    $stmt_insert_notification->execute([$buyer_id, 'auction_won', $winner_message, 'item_detail.php?id=' . $item_id]);

                    // Notify seller
                    $seller_message = "Your item '" . $item_title . "' was sold to " . $buyer_id . " for $" . number_format($final_price, 2) . ".";
                    $stmt_insert_notification->execute([$seller_id, 'item_sold', $seller_message, 'item_detail.php?id=' . $item_id]);

                    // Notify other bidders (outbid)
                    $stmt_other_bidders = $pdo->prepare("SELECT DISTINCT bidder_id FROM bids WHERE item_id = ? AND bidder_id != ?");
                    $stmt_other_bidders->execute([$item_id, $buyer_id]);
                    $other_bidders = $stmt_other_bidders->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($other_bidders as $bidder_id) {
                        $outbid_message = "The auction for '" . $item_title . "' has ended. You were outbid.";
                        $stmt_insert_notification->execute([$bidder_id, 'auction_lost', $outbid_message, 'item_detail.php?id=' . $item_id]);
                    }

                } else {
                    echo "  Item #{$item_id}: Closed (Reserve not met). Highest bid: $" . number_format($current_bid, 2) . ".\n";
                    // Notify seller (reserve not met)
                    $seller_message = "Your item '" . $item_title . "' closed. Reserve price was not met. Highest bid: $" . number_format($current_bid, 2) . ".";
                    $stmt_insert_notification->execute([$seller_id, 'reserve_not_met', $seller_message, 'item_detail.php?id=' . $item_id]);

                    // Notify all bidders (auction lost / reserve not met)
                    $stmt_all_bidders = $pdo->prepare("SELECT DISTINCT bidder_id FROM bids WHERE item_id = ?");
                    $stmt_all_bidders->execute([$item_id]);
                    $all_bidders = $stmt_all_bidders->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($all_bidders as $bidder_id) {
                        $message_for_bidder = "The auction for '" . $item_title . "' has ended. Reserve price was not met.";
                        $stmt_insert_notification->execute([$bidder_id, 'auction_lost', $message_for_bidder, 'item_detail.php?id=' . $item_id]);
                    }
                }
            } else {
                echo "  Item #{$item_id}: Closed (No bids or no highest bidder). \n";
                // Notify seller (no bids)
                $seller_message = "Your item '" . $item_title . "' closed with no bids.";
                $stmt_insert_notification->execute([$seller_id, 'no_bids', $seller_message, 'item_detail.php?id=' . $item_id]);
            }

            // Update the item status in the 'items' table
            $stmt_update_item = $pdo->prepare("UPDATE items SET status = ? WHERE id = ?");
            $stmt_update_item->execute([$new_status, $item_id]);

            // If sold, record in 'sold_items' table
            if ($new_status === 'sold') {
                $stmt_insert_sold = $pdo->prepare("
                    INSERT INTO sold_items (item_id, buyer_id, seller_id, final_price, payment_status, shipping_status)
                    VALUES (?, ?, ?, ?, 'pending', 'pending')
                    ON DUPLICATE KEY UPDATE
                        buyer_id = VALUES(buyer_id),
                        seller_id = VALUES(seller_id),
                        final_price = VALUES(final_price),
                        payment_status = VALUES(payment_status),
                        shipping_status = VALUES(shipping_status)
                ");
                $stmt_insert_sold->execute([$item_id, $buyer_id, $seller_id, $final_price]);
            }

            $pdo->commit(); // Commit transaction for this auction
        }
    }
    echo "Auction closing process completed successfully.\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // Rollback any open transaction on error
    }
    error_log("Auction closing script error: " . $e->getMessage());
    echo "An error occurred during the auction closing process. Check logs for details.\n";
}

?>
