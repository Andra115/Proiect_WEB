<?php
$totalStorage = 1024;
$usedStorage = 614;
$percentageUsed = ($usedStorage / $totalStorage) * 100;
?>
<footer class="main-footer">
    <div class="storage-info">
        <div class="storage-bar-container">
            <div class="storage-bar" style="width: <?php echo $percentageUsed; ?>%"></div>
        </div>
        <div class="storage-text">
            <?php echo $usedStorage; ?>GB used of <?php echo $totalStorage; ?>GB
        </div>
    </div>
</footer> 