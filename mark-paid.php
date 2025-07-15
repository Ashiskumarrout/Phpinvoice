<?php
include 'db.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("UPDATE bills SET status='Paid' WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo "✅ Bill marked as Paid";
    } else {
        echo "❌ Failed to update";
    }
}
?>
