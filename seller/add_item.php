<?php
include "../config/db.php";
include "../includes/auth.php";

if ($_POST) {
    $title = $_POST['title'];
    $desc = $_POST['description'];
    $price = $_POST['start_price'];
    $end = $_POST['end_time'];
    $seller = $_SESSION['user_id'];

    $conn->query("INSERT INTO auctions 
        (seller_id,title,description,start_price,current_price,end_time)
        VALUES ('$seller','$title','$desc','$price','$price','$end')");
}
?>

<form method="post">
    <input name="title" placeholder="Item title" required>
    <textarea name="description"></textarea>
    <input name="start_price" type="number" step="0.01" required>
    <input name="end_time" type="datetime-local" required>
    <button>Add Auction</button>
</form>
