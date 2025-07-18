<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) { header("Location: index.php"); exit; }

// Fetch clients
$clients = $conn->query("SELECT id, company_name, address FROM clients");

// Get next Invoice #
$invoiceQuery = $conn->query("SHOW TABLE STATUS LIKE 'bills'");
$invoiceRow = $invoiceQuery->fetch_assoc();
$nextInvoiceNumber = $invoiceRow['Auto_increment'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_POST['client_id'];
    $bill_date = $_POST['bill_date'] ?: date('Y-m-d');
    $payment_type = $_POST['payment_type'];
    $payment_mode = $_POST['payment_mode'];
    $estimated = floatval($_POST['estimated_value']);
    $remaining_amount = floatval($_POST['remaining_amount']);
    $next_renewal = $_POST['next_renewal'] ?: NULL;

    // Combine items
    $descriptions = $_POST['item_desc'];
    $amounts = $_POST['item_amount'];
    $totalAmount = 0;
    $itemDetails = [];

    foreach ($amounts as $index => $amt) {
        $amt = floatval($amt);
        $desc = htmlspecialchars(trim($descriptions[$index]));
        if ($amt > 0 && $desc != '') {
            $itemDetails[] = $desc . " (" . number_format($amt, 2) . ")";
            $totalAmount += $amt;
        }
    }

    $description = implode(", ", $itemDetails);
    $total = $totalAmount;
    $logoPath = 'uploads/default_logo.png';

    $stmt = $conn->prepare("INSERT INTO bills 
        (client_id, bill_date, amount, total, description, project_type, logo, payment_type, payment_mode, estimated_value, remaining_amount, next_payment_date) 
        VALUES (?, ?, ?, ?, ?, 'One Time', ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isddssssdds", 
        $client_id, $bill_date, $totalAmount, $total, $description, $logoPath, $payment_type, $payment_mode, $estimated, $remaining_amount, $next_renewal
    );

    if ($stmt->execute()) {
        echo "<script>alert('✅ One-Time Bill Added'); window.location='bill-history.php';</script>";
        exit;
    } else {
        echo "<div style='color:red;'>❌ Error: " . $stmt->error . "</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Add One-Time Bill (No GST)</title>
<link rel="stylesheet" href="bootstrap.min.css">
<style>
body {margin:0;font-family:'Segoe UI';background:#f0f2f5;display:flex;}
.sidebar {width:250px;background:linear-gradient(135deg,#7F00FF,#E100FF);color:white;min-height:100vh;padding:20px;position:fixed;}
.sidebar a {color:white;display:block;padding:12px 0;text-decoration:none;font-weight:500;}
.sidebar a:hover {background:#512da8;border-radius:6px;padding-left:12px;}
.main-content {margin-left:250px;padding:40px;width:100%;}
.form-container {background:white;padding:30px;border-radius:16px;box-shadow:0 8px 30px rgba(0,0,0,0.1);max-width:950px;margin:auto;}
.item-row {display:flex;gap:10px;margin-bottom:10px;align-items:center;}
.item-row textarea {flex:2;}
.item-row input {flex:1;}
.item-row button {background:#dc3545;border:none;color:white;border-radius:50%;width:36px;height:36px;font-size:18px;cursor:pointer;}
.add-btn, .save-btn {
    background: linear-gradient(135deg,#7F00FF,#E100FF);
    color:white;
    border:none;
    padding:12px;
    font-weight:bold;
    border-radius:8px;
    width:100%;
    margin-top:10px;
    font-size:16px;
    transition:0.3s;
}
.add-btn:hover, .save-btn:hover {transform:scale(1.02);}
.total-box {background:#f7f7f7;padding:15px;border-radius:8px;margin-top:15px;}
form input, form select, form textarea {
    width: 100%;
    padding: 12px;
    margin: 8px 0;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 15px;
    transition: 0.3s;
}
form input:focus, form select:focus, form textarea:focus {
    border-color: #7F00FF;
    box-shadow: 0 0 8px rgba(127, 0, 255, 0.2);
    outline: none;
}
#bill-to-box {
    background:#fafafa;
    border:1px solid #ddd;
    border-radius:8px;
    padding:15px;
    margin-bottom:20px;
    box-shadow:0 4px 10px rgba(0,0,0,0.05);
}
#bill-to-box strong {display:block;margin-bottom:10px;font-size:16px;color:#333;}
</style>
</head>
<body>
<div class="sidebar">
<h3>🧾 Admin Panel</h3>
<a href="dashboard.php">🏠 Dashboard</a>
<a href="add-client.php">➕ Add Client</a>
<a href="client-list.php">📋 Client List</a>
<a href="add-one-time-billnogst.php">🧾 Add One-Time Bill</a>
<a href="add-recurring-billnogst.php">🔁 Add Recurring Bill</a>
  <a href="re-nogst.php">🔁 Add Recurring No GST</a>
  <a href="one-nogst.php">🧾 One-Time No GST</a>
<a href="bill-history.php">📊 Bill History</a>
<a href="logout.php">🚪 Logout</a>
</div>
<div class="main-content">
<div class="form-container">
<h2 class="text-center mb-4">➕ Add One-Time Bill (No GST)</h2>
<h5>INVOICE # <?php echo $nextInvoiceNumber; ?></h5>
<form method="post">

<div class="form-group">
<label>Estimated Project Value</label>
<input type="number" step="0.01" name="estimated_value" class="form-control" oninput="updateRemaining()">
</div>

<div class="form-group">
<label>Client</label>
<select name="client_id" id="client_id" class="form-control" required onchange="updateBillTo()">
<option value="">Select Client</option>
<?php while($row=$clients->fetch_assoc()) {
    echo "<option value='{$row['id']}' data-company='{$row['company_name']}' data-address='{$row['address']}'>{$row['company_name']}</option>";
} ?>
</select>
</div>

<div id="bill-to-box">
<strong>BILL TO:</strong>
<p id="bill-company">--</p>
<p id="bill-address">--</p>
</div>

<!-- Dynamic Items -->
<div id="items-container">
<div class="item-row">
<textarea name="item_desc[]" class="form-control" placeholder="Description"></textarea>
<input type="number" name="item_amount[]" class="form-control" placeholder="Amount" oninput="calculateTotal()">
<button type="button" onclick="removeRow(this)">×</button>
</div>
</div>
<button type="button" class="add-btn" onclick="addItem()">➕ Add New Item</button>

<div class="form-group">
<label>Payment Type</label>
<select name="payment_type" class="form-control">
<option>Advance</option>
<option>Final</option>
</select>
</div>

<div class="form-group">
<label>Payment Mode</label>
<select name="payment_mode" class="form-control">
<option>Cash</option>
<option>UPI</option>
<option>Cheque</option>
<option>Bank Transfer</option>
</select>
</div>

<div class="form-group">
<label>Bill Date</label>
<input type="date" name="bill_date" class="form-control">
</div>

<div class="form-group">
<label>Next Renewal Date</label>
<input type="date" name="next_renewal" class="form-control">
</div>

<div class="total-box">
<p><strong>Total:</strong> ₹ <span id="grand-total">0.00</span></p>
<p><strong>Remaining Amount:</strong> ₹ <input type="number" step="0.01" name="remaining_amount" readonly class="form-control" style="display:inline-block;width:auto;"></p>
</div>

<button type="submit" class="save-btn">💾 Save One-Time Bill</button>
</form>
</div>
</div>

<script>
function updateBillTo() {
    let select = document.getElementById('client_id');
    let company = select.options[select.selectedIndex].getAttribute('data-company') || '--';
    let address = select.options[select.selectedIndex].getAttribute('data-address') || '--';
    document.getElementById('bill-company').innerText = company;
    document.getElementById('bill-address').innerText = address;
}

function addItem() {
    let container = document.getElementById('items-container');
    let row = document.createElement('div');
    row.classList.add('item-row');
    row.innerHTML = `
        <textarea name="item_desc[]" class="form-control" placeholder="Description"></textarea>
        <input type="number" name="item_amount[]" class="form-control" placeholder="Amount" oninput="calculateTotal()">
        <button type="button" onclick="removeRow(this)">×</button>
    `;
    container.appendChild(row);
}

function removeRow(btn) {
    btn.parentElement.remove();
    calculateTotal();
}

function calculateTotal() {
    let amounts = document.querySelectorAll('input[name="item_amount[]"]');
    let total = 0;
    amounts.forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    document.getElementById('grand-total').innerText = total.toFixed(2);
    updateRemaining();
}

function updateRemaining() {
    let estimated = parseFloat(document.querySelector('input[name="estimated_value"]').value) || 0;
    let grandTotal = parseFloat(document.getElementById('grand-total').innerText) || 0;
    let remaining = estimated - grandTotal;
    document.querySelector('input[name="remaining_amount"]').value = remaining.toFixed(2);
}
</script>
</body>
</html>
