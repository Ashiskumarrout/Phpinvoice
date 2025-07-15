<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) header("Location: index.php");

$bill_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$clients = $conn->query("SELECT * FROM clients");

// Fetch Bill Details
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = $_POST['client_id'];
    $bill_date = $_POST['bill_date'];
    $project_type = $_POST['project_type'];
    $description = $_POST['description'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $gst = floatval($_POST['gst'] ?? 0);
    $apply_gst = isset($_POST['apply_gst']) ? 1 : 0;
    $payment_type = $_POST['payment_type'] ?? null;
    $payment_mode = $_POST['payment_mode'] ?? null;
    $next_payment = $_POST['next_payment'] ?: null;

    $total = $amount + ($apply_gst ? ($amount * $gst / 100) : 0);

    $stmt = $conn->prepare("UPDATE bills 
        SET client_id=?, bill_date=?, amount=?, gst=?, total=?, description=?, project_type=?, apply_gst=?, payment_type=?, payment_mode=?, next_payment_date=? 
        WHERE id=?");
    $stmt->bind_param("isdddsssissi", $client_id, $bill_date, $amount, $gst, $total, $description, $project_type, $apply_gst, $payment_type, $payment_mode, $next_payment, $bill_id);

    if ($stmt->execute()) {
        echo "<script>alert('✅ Bill updated successfully'); location.href='bill-history.php';</script>";
    } else {
        echo "<script>alert('❌ Failed to update bill');</script>";
    }
    exit;
} else {
    $result = $conn->query("SELECT * FROM bills WHERE id = $bill_id");
    if ($result && $result->num_rows > 0) {
        $bill = $result->fetch_assoc();
    } else {
        echo "<script>alert('Bill not found!'); location.href='bill-history.php';</script>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Bill</title>
    <link rel="stylesheet" href="bootstrap.min.css">
    <style>
        body { font-family: Arial; background: #f4f4f4; margin: 0; }
        .sidebar {
            width: 250px; background: linear-gradient(135deg, #7F00FF, #E100FF);
            position: fixed; top: 0; left: 0; min-height: 100vh; color: white; padding: 20px;
        }
        .sidebar a { color: #fff; display: block; padding: 12px 10px; text-decoration: none; font-weight: bold; }
        .sidebar a:hover { background: rgba(255,255,255,0.2); border-radius: 8px; }
        .main-content { margin-left: 260px; padding: 30px; }
        .form-container {
            background: #fff; padding: 30px; border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            max-width: 700px; margin: auto;
        }
        .btn-primary {
            background: linear-gradient(135deg, #7F00FF, #E100FF);
            border: none; padding: 12px; font-size: 16px; font-weight: bold; border-radius: 25px;
        }
        .btn-primary:hover { opacity: 0.9; }
        label { font-weight: bold; }
    </style>
</head>
<body>

<div class="sidebar">
    <h3>Admin Panel</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="add-client.php">➕ Add Client</a>
    <a href="client-list.php">📄 Client List</a>
    <a href="add-bill.php">🧾 Add New Bill</a>
    <a href="bill-history.php">📊 Bill History</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<div class="main-content">
    <div class="form-container">
        <h2 class="mb-4 text-center">✏️ Edit Bill</h2>
        <form method="post" id="editBillForm">
            
            <label>Client:</label>
            <select name="client_id" class="form-control mb-3" required>
                <?php while ($client = $clients->fetch_assoc()) {
                    $selected = $bill['client_id'] == $client['id'] ? 'selected' : '';
                    echo "<option value='{$client['id']}' $selected>{$client['company_name']}</option>";
                } ?>
            </select>

            <label>Project Type:</label>
            <select name="project_type" id="projectType" class="form-control mb-3" required>
                <option value="one_time" <?= $bill['project_type'] == 'one_time' ? 'selected' : '' ?>>One Time</option>
                <option value="recurring" <?= $bill['project_type'] == 'recurring' ? 'selected' : '' ?>>Recurring</option>
            </select>

            <label>Bill Date:</label>
            <input type="date" name="bill_date" class="form-control mb-3" value="<?= htmlspecialchars($bill['bill_date']) ?>" required>

            <label>Amount:</label>
            <input type="number" name="amount" id="amount" step="0.01" class="form-control mb-3" value="<?= htmlspecialchars($bill['amount']) ?>" required>

            <label>GST (%):</label>
            <input type="number" name="gst" id="gst" step="0.01" class="form-control mb-3" value="<?= htmlspecialchars($bill['gst']) ?>">

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="apply_gst" id="apply_gst" <?= $bill['apply_gst'] ? 'checked' : '' ?>>
                <label class="form-check-label">Apply GST</label>
            </div>

            <label>Total:</label>
            <input type="text" id="total" class="form-control mb-3" value="<?= htmlspecialchars($bill['total']) ?>" readonly>

            <label>Description:</label>
            <textarea name="description" class="form-control mb-3" rows="3" required><?= htmlspecialchars($bill['description']) ?></textarea>

            <div id="paymentTypeGroup">
                <label>Payment Type:</label>
                <select name="payment_type" class="form-control mb-3">
                    <option <?= $bill['payment_type'] == 'Advance' ? 'selected' : '' ?>>Advance</option>
                    <option <?= $bill['payment_type'] == 'Final' ? 'selected' : '' ?>>Final</option>
                </select>
            </div>

            <label>Payment Mode:</label>
            <select name="payment_mode" class="form-control mb-3">
                <option <?= $bill['payment_mode'] == 'Cash' ? 'selected' : '' ?>>Cash</option>
                <option <?= $bill['payment_mode'] == 'UPI' ? 'selected' : '' ?>>UPI</option>
                <option <?= $bill['payment_mode'] == 'Cheque' ? 'selected' : '' ?>>Cheque</option>
                <option <?= $bill['payment_mode'] == 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
            </select>

            <label>Next Payment Date:</label>
            <input type="date" name="next_payment" class="form-control mb-4" value="<?= htmlspecialchars($bill['next_payment_date']) ?>">

            <button class="btn btn-primary w-100">Update Bill</button>
        </form>
    </div>
</div>

<script>
    // Hide Payment Type if Recurring
    const projectType = document.getElementById('projectType');
    const paymentTypeGroup = document.getElementById('paymentTypeGroup');

    function togglePaymentType() {
        if (projectType.value === 'recurring') {
            paymentTypeGroup.style.display = 'none';
        } else {
            paymentTypeGroup.style.display = 'block';
        }
    }
    projectType.addEventListener('change', togglePaymentType);
    togglePaymentType();

    // Auto Calculate Total
    const amount = document.getElementById('amount');
    const gst = document.getElementById('gst');
    const apply_gst = document.getElementById('apply_gst');
    const total = document.getElementById('total');

    function calculateTotal() {
        let amt = parseFloat(amount.value) || 0;
        let gstVal = apply_gst.checked ? parseFloat(gst.value) || 0 : 0;
        let calcTotal = amt + (amt * gstVal / 100);
        total.value = calcTotal.toFixed(2);
    }
    amount.addEventListener('input', calculateTotal);
    gst.addEventListener('input', calculateTotal);
    apply_gst.addEventListener('change', calculateTotal);
</script>

</body>
</html>
