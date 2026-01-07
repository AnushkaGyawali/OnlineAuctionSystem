<?php
include "../config/db.php";
$result = $conn->query("SELECT * FROM auctions WHERE status='active'");
?>

<h2>Active Auctions</h2>

<?php while($row = $result->fetch_assoc()): ?>
    <div>
        <h4><?= $row['title'] ?></h4>
        <p>Current Price: <?= $row['current_price'] ?></p>
        <a href="view.php?id=<?= $row['id'] ?>">View</a>
    </div>
<?php endwhile; ?>
<div class="container mt-4">
    <h3>Active Auctions</h3>

    <div class="row">
        <?php while($row = $result->fetch_assoc()): ?>
        <div class="col-md-4 mb-3">
            <div class="card p-2">
                <h5><?= $row['title'] ?></h5>
                <p>Current: <?= $row['current_price'] ?></p>
                <a href="view.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">View</a>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>
