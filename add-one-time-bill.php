<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) { header("Location: index.php"); exit; }

$clients = $conn->query("SELECT id, company_name FROM clients");

// Get next Invoice #
$invoiceQuery = $conn->query("SHOW TABLE STATUS LIKE 'bills'");
$invoiceRow = $invoiceQuery->fetch_assoc();
$nextInvoiceNumber = $invoiceRow['Auto_increment'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_POST['client_id'];
    $description = $_POST['description'];
    $estimated = floatval($_POST['estimated_value']);
    $amount = floatval($_POST['amount']);
    $payment_type = $_POST['payment_type'];
    $payment_mode = $_POST['payment_mode'];
    $bill_date = $_POST['bill_date'] ?: date('Y-m-d');
    $gst = isset($_POST['gst']) ? 18 : 0;
    $apply_gst = $gst ? 1 : 0;

    $total = $amount + ($apply_gst ? ($amount * 18 / 100) : 0);
    $logoPath = 'uploads/default_logo.png'; // Fixed

    $stmt = $conn->prepare("INSERT INTO bills 
        (client_id, bill_date, amount, gst, total, description, project_type, logo, apply_gst, payment_type, payment_mode) 
        VALUES (?, ?, ?, ?, ?, ?, 'One Time', ?, ?, ?, ?)");
    $stmt->bind_param("isdddssiss", $client_id, $bill_date, $amount, $gst, $total, $description, $logoPath, $apply_gst, $payment_type, $payment_mode);

    if ($stmt->execute()) {
        echo "<script>alert('✅ Bill Added'); window.location='bill-history.php';</script>";
        exit;
    } else {
        echo "<div style='color:red;'>❌ Error: " . $stmt->error . "</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Add One-Time Bill</title>
<link rel="stylesheet" href="bootstrap.min.css">
<style>
body {margin:0;font-family:'Segoe UI';background:#f0f2f5;display:flex;}
.sidebar {width:250px;background:linear-gradient(135deg,#7F00FF,#E100FF);color:white;min-height:100vh;padding:20px;position:fixed;}
.sidebar a {color:white;display:block;padding:12px 0;text-decoration:none;font-weight:500;}
.sidebar a:hover {background:#512da8;border-radius:6px;padding-left:12px;}
.main-content {margin-left:250px;padding:40px;width:100%;}
.form-container {background:white;padding:40px;border-radius:16px;box-shadow:0 8px 30px rgba(0,0,0,0.1);max-width:750px;margin:auto;}
.form-group {margin-bottom:20px;}
label {font-weight:600;}
</style>
</head>
<body>
<div class="sidebar">
<h3>🧾 Admin Panel</h3>
<a href="dashboard.php">🏠 Dashboard</a>
<a href="add-client.php">➕ Add Client</a>
<a href="client-list.php">📋 Client List</a>
<a href="add-one-time-bill.php">🧾 Add One-Time Bill</a>
<a href="add-recurring-bill.php">🔁 Add Recurring Bill</a>
<a href="bill-history.php">📊 Bill History</a>
<a href="logout.php">🚪 Logout</a>
</div>
<div class="main-content">
<div class="form-container">
<h2 class="text-center mb-4">➕ Add One-Time Bill</h2>
<h5>INVOICE # <?php echo $nextInvoiceNumber; ?></h5>
<form method="post">
<div class="form-group">
<label>Client</label>
<select name="client_id" class="form-control" required>
<option value="">Select Client</option>
<?php while($row=$clients->fetch_assoc()) echo "<option value='{$row['id']}'>{$row['company_name']}</option>"; ?>
</select>
</div>
<div class="form-group">
<label><input type="checkbox" name="gst"> Apply GST (18%)</label>
</div>
<div class="form-group">
<label>Total Estimated Project Value</label>
<input type="number" step="0.01" name="estimated_value" class="form-control">
</div>
<div class="form-group">
<label>Description</label>
<textarea name="description" class="form-control"></textarea>
</div>
<div class="form-group">
<label>Payment Type</label>
<select name="payment_type" class="form-control">
<option>Advance</option><option>Final</option>
</select>
</div>
<div class="form-group">
<label>Amount Paid</label>
<input type="number" step="0.01" name="amount" class="form-control">
</div>
<div class="form-group">
<label>Payment Mode</label>
<select name="payment_mode" class="form-control">
<option>Cash</option><option>UPI</option><option>Cheque</option><option>Bank Transfer</option>
</select>
</div>
<div class="form-group">
<label>Bill Date</label>
<input type="date" name="bill_date" class="form-control">
</div>
<button type="submit" class="btn btn-primary w-100">➕ Submit Bill</button>
</form>
</div>
</div>
</body>
</html>
