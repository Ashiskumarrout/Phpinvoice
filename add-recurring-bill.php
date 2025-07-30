<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) { header("Location: index.php"); exit; }

// Fetch clients
$clients = $conn->query("SELECT id, company_name, gst_number, address FROM clients");

// Invoice #
$invoiceQuery = $conn->query("SHOW TABLE STATUS LIKE 'bills'");
$invoiceRow = $invoiceQuery->fetch_assoc();
$nextInvoiceNumber = $invoiceRow['Auto_increment'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_POST['client_id'];
    $bill_date = $_POST['bill_date'] ?: date('Y-m-d');
    $next_payment_date = $_POST['next_payment'] ?: NULL;
    $payment_mode = $_POST['payment_mode'];
    $gst = isset($_POST['gst']) ? 18 : 0;
    $apply_gst = $gst ? 1 : 0;

    // Combine items
    $descriptions = $_POST['item_desc'];
    $amounts = $_POST['item_amount'];
    $totalAmount = 0;
    $itemDetails = [];

    foreach ($amounts as $index => $amt) {
        $amt = floatval($amt);
        // Normalize description by replacing line breaks with spaces and trimming
        $desc = htmlspecialchars(preg_replace('/\s+/', ' ', trim($descriptions[$index])));
        if ($amt > 0 && $desc != '') {
            $itemDetails[] = $desc . " (" . number_format($amt, 2) . ")";
            $totalAmount += $amt;
        }
    }

    $description = implode(", ", $itemDetails);
    $total = $totalAmount + ($apply_gst ? ($totalAmount * $gst / 100) : 0);
    $logoPath = 'uploads/default_logo.png';

    $stmt = $conn->prepare("INSERT INTO bills 
        (client_id, bill_date, amount, gst, total, description, project_type, logo, apply_gst, payment_mode, next_payment_date) 
        VALUES (?, ?, ?, ?, ?, ?, 'Recurring', ?, ?, ?, ?)");
    $stmt->bind_param("isdddssiss", $client_id, $bill_date, $totalAmount, $gst, $total, $description, $logoPath, $apply_gst, $payment_mode, $next_payment_date);

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
body {
    margin:0;
    font-family:'Segoe UI';
    background:#f0f2f5;
    display:flex;
}
.sidebar {
    width:250px;
    background:linear-gradient(135deg,#7F00FF,#E100FF);
    color:white;
    min-height:100vh;
    padding:20px;
    position:fixed;
}
.sidebar a {
    color:white;
    display:block;
    padding:12px 0;
    text-decoration:none;
    font-weight:500;
    transition:0.3s;
}
.sidebar a:hover {
    background:#512da8;
    border-radius:6px;
    padding-left:12px;
}
.main-content {
    margin-left:250px;
    padding:40px;
    width:100%;
}
.form-container {
    background:white;
    padding:30px;
    border-radius:16px;
    box-shadow:0 8px 30px rgba(0,0,0,0.1);
    max-width:950px;
    margin:auto;
}
.item-row {
    display:flex;
    gap:10px;
    margin-bottom:10px;
    align-items:center;
}
.item-row textarea {
    flex:2;
    padding:10px;
    border-radius:8px;
    border:1px solid #ccc;
}
.item-row input {
    flex:1;
    padding:10px;
    border-radius:8px;
    border:1px solid #ccc;
}
.item-row button {
    background:#dc3545;
    border:none;
    color:white;
    border-radius:50%;
    width:36px;
    height:36px;
    font-size:18px;
    cursor:pointer;
}
.add-btn, .save-btn {
    background:linear-gradient(135deg,#7F00FF,#E100FF);
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
.add-btn:hover, .save-btn:hover {
    transform:scale(1.02);
}
.total-box {
    background:#f7f7f7;
    padding:15px;
    border-radius:8px;
    margin-top:15px;
}
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
#bill-to-box strong {
    display:block;
    margin-bottom:10px;
    font-size:16px;
    color:#333;
}
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
while($row=$clients->fetch_assoc()) {
    echo "<option value='{$row['id']}' data-company='{$row['company_name']}' data-gst='{$row['gst_number']}' data-address='{$row['address']}'>{$row['company_name']}</option>";
}
?>
</select>
</div>

<div id="bill-to-box">
<strong>BILL TO:</strong>
<p id="bill-company">--</p>
<p><strong>GST:</strong> <span id="bill-gst">--</span></p>
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

<div class="form-group mt-3">
<label>Bill Date</label>
<input type="date" name="bill_date" class="form-control">
</div>

<div class="wrap">
    <label for="" style="font-size:18px; font-weight:bold; color:#7F00FF;">Apply GST (18%)</label>
    <div class="form-group">
        <label>
            <input type="checkbox" name="gst" onchange="calculateTotal()" class="big-checkbox">
        </label>
    </div>
</div>

<style>
.wrap {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

/* Bigger Checkbox with Custom Color */
.big-checkbox {
    width: 28px;
    height: 28px;
    accent-color: #7F00FF; /* Purple gradient main color */
    cursor: pointer;
    border-radius: 6px;
}
</style>

<div class="form-group">
<label>Payment Mode</label>
<select name="payment_mode" class="form-control">
<option>Cash</option><option>UPI</option><option>Cheque</option><option>Bank Transfer</option>
</select>
</div>

<div class="form-group">
<label>Next Payment Date</label>
<input type="date" name="next_payment" class="form-control">
</div>

<!-- Totals -->
<div class="total-box">
<p><strong>Subtotal:</strong> ₹ <span id="subtotal">0.00</span></p>
<p><strong>GST (18%):</strong> ₹ <span id="gst-amount">0.00</span></p>
<p><strong>Grand Total:</strong> ₹ <span id="grand-total">0.00</span></p>
</div>

<button type="submit" class="save-btn">💾 Save Recurring Bill</button>
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
    let subtotal = 0;
    amounts.forEach(input => {
        subtotal += parseFloat(input.value) || 0;
    });
    let gstChecked = document.querySelector('input[name="gst"]').checked;
    let gstAmount = gstChecked ? (subtotal * 18 / 100) : 0;
    let grandTotal = subtotal + gstAmount;
    document.getElementById('subtotal').innerText = subtotal.toFixed(2);
    document.getElementById('gst-amount').innerText = gstAmount.toFixed(2);
    document.getElementById('grand-total').innerText = grandTotal.toFixed(2);
}
</script>
</body>
</html>
