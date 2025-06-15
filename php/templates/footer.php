<?php
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /../../login.php");
    exit;
}
$user_id = $_SESSION['user_id'];

try{
$stmt = $pdo->prepare("SELECT * FROM get_user_storage(:user_id)");
$stmt->execute(['user_id' => $user_id]);
$storage = $stmt->fetch(PDO::FETCH_ASSOC);
}catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$total_storage= $storage['total_storage'];
$space_available = $storage['total_available'];
$used_storage = $total_storage-$space_available;

$total_storage_gb = $total_storage / (1024 ** 3);
$space_available_gb = $space_available / (1024 ** 3);
$used_storage_gb = $used_storage / (1024 ** 3);

$percentageUsed = ($used_storage_gb / $total_storage_gb) * 100;
?>
<footer class="main-footer">
    <div class="storage-info">
        <div class="storage-bar-container">
            <div class="storage-bar" style="width: <?php echo $percentageUsed; ?>%"></div>
        </div>
        <div class="storage-text">
            <?php echo round($used_storage_gb, 3); ?>GB of <?php echo round($total_storage_gb, 3); ?>GB used
        </div>
    </div>
</footer> 