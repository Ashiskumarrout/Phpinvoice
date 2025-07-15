<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) header("Location: index.php");

$bill_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch Bill & Client Details
$sql = "SELECT b.*, c.company_name, c.email, c.phone 
        FROM bills b 
        JOIN clients c ON b.client_id = c.id 
        WHERE b.id = $bill_id";
$res = $conn->query($sql);
if (!$res || $res->num_rows == 0) {
    echo "<script>alert('Bill not found!'); location.href='dashboard.php';</script>";
    exit;
}
$bill = $res->fetch_assoc();

// Mark as Paid
if (isset($_POST['mark_paid'])) {
    $conn->query("UPDATE bills SET status='Paid' WHERE id=$bill_id");
    echo "<script>alert('✅ Bill marked as Paid'); location.href='dashboard.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Bill Details</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.container { max-width: 700px; margin-top: 40px; }
.card { border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
.btn-primary { background: linear-gradient(135deg,#7F00FF,#E100FF); border:none; }
.btn-danger { background:#dc3545; border:none; }
</style>
</head>
<body>
<div class="container">
    <div class="card p-4">
        <h3 class="mb-3 text-center">Bill Details</h3>
        <table class="table">
            <tr><th>Client</th><td><?= htmlspecialchars($bill['company_name']) ?></td></tr>
            <tr><th>Email</th><td><?= htmlspecialchars($bill['email']) ?></td></tr>
            <tr><th>Phone</th><td><?= htmlspecialchars($bill['phone']) ?></td></tr>
            <tr><th>Project Type</th><td><?= htmlspecialchars($bill['project_type']) ?></td></tr>
            <tr><th>Amount</th><td>₹<?= number_format($bill['amount'],2) ?></td></tr>
            <tr><th>GST (%)</th><td><?= htmlspecialchars($bill['gst']) ?></td></tr>
            <tr><th>Total</th><td>₹<?= number_format($bill['total'],2) ?></td></tr>
            <tr><th>Payment Type</th><td><?= htmlspecialchars($bill['payment_type']) ?></td></tr>
            <tr><th>Payment Mode</th><td><?= htmlspecialchars($bill['payment_mode']) ?></td></tr>
            <tr><th>Next Payment Date</th><td><?= htmlspecialchars($bill['next_payment_date']) ?></td></tr>
            <tr><th>Status</th>
                <td>
                    <?php if ($bill['status']=='Paid'): ?>
                        <span class="badge bg-success">Paid</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Pending</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <form method="post" class="text-center mt-3">
            <?php if ($bill['status']!='Paid'): ?>
                <button type="submit" name="mark_paid" class="btn btn-primary w-100">✔ Mark as Paid</button>
            <?php else: ?>
                <a href="dashboard.php" class="btn btn-secondary w-100">← Back to Dashboard</a>
            <?php endif; ?>
        </form>
    </div>
</div>
</body>
</html>
