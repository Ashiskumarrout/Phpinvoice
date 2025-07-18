<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("Location: client-list.php?msg=deleted");
            exit;
        } else {
            echo "Error deleting client.";
        }
    } else {
        echo "Invalid ID.";
    }
} else {
    echo "ID not provided.";
}
?>
