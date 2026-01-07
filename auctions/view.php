<?php
include "../config/db.php";
$id = $_GET['id'];

$auction = $conn->query("SELECT * FROM auctions WHERE id=$id")->fetch_assoc();

if ($_POST) {
    $bid = $_POST['bid_amount'];
    if ($bid > $auction['current_price']) {
        $conn->query("INSERT INTO bids (auction_id,bidder_id,bid_amount)
                      VALUES ($id,{$_SESSION['user_id']},$bid)");
        $conn->query("UPDATE auctions SET current_price=$bid WHERE id=$id");
    }
}
?>

<h2><?= $auction['title'] ?></h2>
<p><?= $auction['description'] ?></p>
<p>Current Price: <?= $auction['current_price'] ?></p>

<form method="post">
    <input type="number" step="0.01" name="bid_amount" required>
    <button>Place Bid</button>
</form>
