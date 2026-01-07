<?php
$conn = new mysqli("localhost", "root", "", "online_auction");

if ($conn->connect_error) {
    die("Database connection failed");
}
session_start();
?>
