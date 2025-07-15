<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) { header("Location: index.php"); exit; }

// Fetch clients with all details
$clients = $conn->query("SELECT id, company_name, gst_number, address FROM clients");

// Get next Invoice #
$invoiceQuery = $conn->query("SHOW TABLE STATUS LIKE 'bills'");
$invoiceRow = $invoiceQuery->fetch_assoc();
$nextInvoiceNumber = $invoiceRow['Auto_increment'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_POST['client_id'];
    $description = $_POST['recurring_description'];
    $amount = floatval($_POST['recurring_amount']);
    $bill_date = $_POST['bill_date'] ?: date('Y-m-d');
    $next_payment_date = $_POST['next_payment'] ?: NULL;
    $payment_mode = $_POST['payment_mode'];
    $gst = isset($_POST['gst']) ? 18 : 0;
    $apply_gst = $gst ? 1 : 0;

    $total = $amount + ($apply_gst ? ($amount * 18 / 100) : 0);
    $logoPath = 'uploads/default_logo.png'; // Fixed logo

    $stmt = $conn->prepare("INSERT INTO bills 
        (client_id, bill_date, amount, gst, total, description, project_type, logo, apply_gst, payment_mode, next_payment_date) 
        VALUES (?, ?, ?, ?, ?, ?, 'Recurring', ?, ?, ?, ?)");
    $stmt->bind_param("isdddssiss", $client_id, $bill_date, $amount, $gst, $total, $description, $logoPath, $apply_gst, $payment_mode, $next_payment_date);

    if ($stmt->execute()) {
        echo "<script>alert('✅ Recurring Bill Added'); window.location='bill-history.php';</script>";
        exit;
    } else {
        echo "<div style='color:red;'>❌ Error: " . $stmt->error . "</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Add Recurring Bill</title>
<link rel="stylesheet" href="bootstrap.min.css">
<style>
body {margin:0;font-family:'Segoe UI';background:#f0f2f5;display:flex;}
.sidebar {width:250px;background:linear-gradient(135deg,#7F00FF,#E100FF);color:white;min-height:100vh;padding:20px;position:fixed;}
.sidebar a {color:white;display:block;padding:12px 0;text-decoration:none;font-weight:500;}
.sidebar a:hover {background:#512da8;border-radius:6px;padding-left:12px;}
.main-content {margin-left:250px;padding:40px;width:100%;}
.form-container {background:white;padding:40px;border-radius:16px;box-shadow:0 8px 30px rgba(0,0,0,0.1);max-width:850px;margin:auto;}
.form-group {margin-bottom:20px;}
label {font-weight:600;}
#bill-to-box {background:#f9f9f9;padding:15px;border-radius:10px;margin-bottom:15px;border:1px solid #ccc;}
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
<h2 class="text-center mb-4">🔁 Add Recurring Bill</h2>
<h5>INVOICE # <?php echo $nextInvoiceNumber; ?></h5>
<form method="post">
<div class="form-group">
<label>Client</label>
<select name="client_id" id="client_id" class="form-control" required onchange="updateBillTo()">
<option value="">Select Client</option>
<?php
$clientData = [];
while($row=$clients->fetch_assoc()) {
    $id = $row['id'];
    $company = htmlspecialchars($row['company_name']);
    $gst = htmlspecialchars($row['gst_number']);
    $address = htmlspecialchars($row['address']);
    echo "<option value='$id' data-company='$company' data-gst='$gst' data-address='$address'>$company</option>";
}
?>
</select>
</div>

<!-- BILL TO Section -->
<div id="bill-to-box">
<strong>BILL TO:</strong>
<p id="bill-company">--</p>
<p><strong>GST:</strong> <span id="bill-gst">--</span></p>
<p id="bill-address">--</p>
</div>

<div class="form-group">
<label>Project Amount</label>
<input type="number" step="0.01" name="recurring_amount" class="form-control" oninput="calculateTotal()" required>
</div>
<div class="form-group">
<label>Bill Date</label>
<input type="date" name="bill_date" class="form-control">
</div>
<div class="form-group">
<label><input type="checkbox" name="gst" onchange="calculateTotal()"> Apply GST (18%)</label>
</div>
<div class="form-group">
<label>Payment Mode</label>
<select name="payment_mode" class="form-control">
<option>Cash</option><option>UPI</option><option>Cheque</option><option>Bank Transfer</option>
</select>
</div>
<div class="form-group">
<label>Description</label>
<textarea name="recurring_description" class="form-control"></textarea>
</div>
<div class="form-group">
<label>Next Payment Date</label>
<input type="date" name="next_payment" class="form-control">
</div>

<!-- Dynamic Total Display -->
<div class="form-group">
<label>Total (with GST if applied)</label>
<input type="text" id="total_amount" class="form-control" readonly>
</div>

<button type="submit" class="btn btn-primary w-100">🔁 Submit Bill</button>
</form>
</div>
</div>

<script>
function updateBillTo() {
    let select = document.getElementById('client_id');
    let company = select.options[select.selectedIndex].getAttribute('data-company') || '--';
    let gst = select.options[select.selectedIndex].getAttribute('data-gst') || '--';
    let address = select.options[select.selectedIndex].getAttribute('data-address') || '--';

    document.getElementById('bill-company').innerText = company;
    document.getElementById('bill-gst').innerText = gst;
    document.getElementById('bill-address').innerText = address;
}

function calculateTotal() {
    let amount = parseFloat(document.querySelector('input[name="recurring_amount"]').value) || 0;
    let gstChecked = document.querySelector('input[name="gst"]').checked;
    let gstRate = 18;
    let total = amount;

    if (gstChecked) {
        total += amount * gstRate / 100;
    }

    document.getElementById('total_amount').value = '₹ ' + total.toFixed(2);
}
</script>
</body>
</html>
