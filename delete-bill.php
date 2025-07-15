<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) header("Location: index.php");

$id = intval($_GET['id'] ?? 0);
if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM bills WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: bill-history.php?msg=Bill deleted successfully");
    } else {
        echo "Error deleting record.";
    }
} else {
    echo "Invalid ID.";
}
?>
