<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) header("Location: index.php");

// Validate Bill ID
$bill_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($bill_id <= 0) {
    echo "<script>alert('Invalid Bill ID'); location.href='bill-history.php';</script>";
    exit;
}

// Fetch all clients for dropdown
$clients = $conn->query("SELECT * FROM clients");

// Fetch Bill Details
$result = $conn->prepare("SELECT * FROM bills WHERE id=?");
$result->bind_param("i", $bill_id);
$result->execute();
$billData = $result->get_result();

if ($billData->num_rows == 0) {
    echo "<script>alert('Bill not found!'); location.href='bill-history.php';</script>";
    exit;
}
$bill = $billData->fetch_assoc();

// Update Bill
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = intval($_POST['client_id']);
    $bill_date = $_POST['bill_date'];
    $project_type = $_POST['project_type'];
    $description = trim($_POST['description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $gst = floatval($_POST['gst'] ?? 0);
    $apply_gst = isset($_POST['apply_gst']) ? 1 : 0;
    $payment_type = $_POST['payment_type'] ?? null;
    $payment_mode = $_POST['payment_mode'] ?? null;
    $next_payment = !empty($_POST['next_payment']) ? $_POST['next_payment'] : null;

    // Calculate Total
    $total = $amount + ($apply_gst ? ($amount * $gst / 100) : 0);

    $stmt = $conn->prepare("UPDATE bills 
        SET client_id=?, bill_date=?, amount=?, gst=?, total=?, description=?, project_type=?, apply_gst=?, payment_type=?, payment_mode=?, next_payment_date=?, updated_at=NOW()
        WHERE id=?");
    $stmt->bind_param("isdddsssissi", $client_id, $bill_date, $amount, $gst, $total, $description, $project_type, $apply_gst, $payment_type, $payment_mode, $next_payment, $bill_id);

    if ($stmt->execute()) {
        echo "<script>alert('✅ Bill updated successfully'); location.href='bill-history.php';</script>";
    } else {
        echo "<script>alert('❌ Failed to update bill');</script>";
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Bill</title>
    <link rel="stylesheet" href="bootstrap.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            display: flex;
        }
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #7F00FF, #E100FF);
            position: fixed;
            top: 0; left: 0;
            min-height: 100vh;
            color: white;
            padding: 20px;
        }
        .sidebar h3 {
            margin-bottom: 20px;
            font-size: 22px;
            font-weight: bold;
        }
        .sidebar a {
            color: #fff;
            display: block;
            padding: 12px 10px;
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
            border-radius: 6px;
        }
        .sidebar a:hover {
            background: rgba(255,255,255,0.2);
            padding-left: 15px;
        }
        .main-content {
            margin-left: 260px;
            padding: 40px;
            width: 100%;
        }
        .form-container {
            background: #fff;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            max-width: 750px;
            margin: auto;
        }
        .form-container h2 {
            margin-bottom: 25px;
            text-align: center;
            font-weight: bold;
            color: #333;
        }
        label {
            font-weight: 600;
            margin-top: 10px;
        }
        input[type="text"], input[type="number"], input[type="date"], select, textarea {
            width: 100%;
            padding: 12px;
            margin-top: 8px;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 15px;
            transition: 0.3s;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #7F00FF;
            box-shadow: 0 0 8px rgba(127, 0, 255, 0.2);
            outline: none;
        }
        textarea {
            resize: none;
        }
        .form-check-label {
            font-weight: 500;
        }
        .btn-primary {
            background: linear-gradient(135deg, #7F00FF, #E100FF);
            border: none;
            padding: 14px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 8px;
            width: 100%;
            color: white;
            transition: 0.3s;
        }
        .btn-primary:hover {
            transform: scale(1.02);
            opacity: 0.9;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h3>🧾 Admin Panel</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="add-client.php">➕ Add Client</a>
    <a href="client-list.php">📄 Client List</a>
    <a href="add-one-time-bill.php">🧾 Add One-Time Bill</a>
    <a href="add-recurring-bill.php">🔁 Add Recurring Bill</a>
    <a href="bill-history.php">📊 Bill History</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<div class="main-content">
    <div class="form-container">
        <h2>✏️ Edit Bill</h2>
        <form method="post">

            <label>Client:</label>
            <select name="client_id" class="form-control mb-3" required>
                <?php while ($client = $clients->fetch_assoc()) {
                    $selected = $bill['client_id'] == $client['id'] ? 'selected' : '';
                    echo "<option value='{$client['id']}' $selected>{$client['company_name']}</option>";
                } ?>
            </select>

            <label>Project Type:</label>
            <select name="project_type" id="projectType" class="form-control mb-3" required>
                <option value="onetime" <?= $bill['project_type'] == 'onetime' ? 'selected' : '' ?>>One Time</option>
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

            <button class="btn btn-primary">💾 Update Bill</button>
        </form>
    </div>
</div>

<script>
    // Hide Payment Type if Recurring
    const projectType = document.getElementById('projectType');
    const paymentTypeGroup = document.getElementById('paymentTypeGroup');

    function togglePaymentType() {
        paymentTypeGroup.style.display = projectType.value === 'recurring' ? 'none' : 'block';
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
