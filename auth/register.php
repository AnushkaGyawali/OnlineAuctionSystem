<?php
include "../config/db.php";

if ($_POST) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    $conn->query("INSERT INTO users (name,email,password,role)
                  VALUES ('$name','$email','$password','$role')");

    header("Location: login.php");
}
?>

<form method="post">
    <input name="name" placeholder="Name" required>
    <input name="email" type="email" required>
    <input name="password" type="password" required>

    <select name="role">
        <option value="buyer">Buyer</option>
        <option value="seller">Seller</option>
    </select>

    <button type="submit">Register</button>
</form>
