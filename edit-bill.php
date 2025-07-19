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

// Fetch Bill Details with Client Info
$result = $conn->prepare("
    SELECT bills.*, clients.company_name, clients.address, clients.gst_number 
    FROM bills 
    JOIN clients ON bills.client_id = clients.id 
    WHERE bills.id = ?
");
$result->bind_param("i", $bill_id);
$result->execute();
$billData = $result->get_result();

if ($billData->num_rows == 0) {
    echo "<script>alert('Bill not found!'); location.href='bill-history.php';</script>";
    exit;
}
$bill = $billData->fetch_assoc();

// Parse existing items from description
$existingItems = [];
if (!empty($bill['description'])) {
    $items = preg_split('/,(?![^\(]*\))/', $bill['description']);
    foreach ($items as $item) {
        $item = trim($item);
        if (preg_match('/^(.*)\(([\d,.]+)\)$/', $item, $matches)) {
            $existingItems[] = [
                'desc' => trim($matches[1]),
                'amount' => str_replace(',', '', $matches[2])
            ];
        } else {
            $existingItems[] = [
                'desc' => $item,
                'amount' => ''
            ];
        }
    }
}

// Update Bill
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = intval($_POST['client_id']);
    
    // Validate bill_date
    $bill_date = !empty($_POST['bill_date']) ? trim($_POST['bill_date']) : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bill_date)) {
        $bill_date = date('Y-m-d');
    }
    
    $project_type = $_POST['project_type'];
    
    // Validate payment_mode
    $payment_mode = $_POST['payment_mode'] ?? 'Cash';
    if (!in_array($payment_mode, ['Cash', 'UPI', 'Cheque', 'Bank Transfer'])) {
        $payment_mode = 'Cash';
    }
    
    // Validate next_payment date
    $next_payment = !empty($_POST['next_payment']) ? trim($_POST['next_payment']) : null;
    if ($next_payment && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $next_payment)) {
        $next_payment = null;
    }
    
    $estimated_value = floatval($_POST['estimated_value'] ?? 0);
    
    // Handle dynamic items
    $descriptions = $_POST['item_desc'] ?? [];
    $amounts = $_POST['item_amount'] ?? [];
    $totalAmount = 0;
    $itemDetails = [];

    foreach ($amounts as $index => $amt) {
        if (!empty($amt) && !empty($descriptions[$index])) {
            $amt = floatval($amt);
            $desc = trim($descriptions[$index]);
            $totalAmount += $amt;
            $itemDetails[] = "$desc($amt)";
        }
    }

    $description = implode(", ", $itemDetails);
    
    // GST calculation
    $gst = floatval($_POST['gst'] ?? 0);
    $apply_gst = isset($_POST['apply_gst']) ? 1 : 0;
    $gstAmount = $apply_gst ? ($totalAmount * $gst / 100) : 0;
    $total = $totalAmount + $gstAmount;

    // Simple payment_type handling
    $payment_type = null;
    if ($project_type === 'One Time') {
        $raw_payment_type = $_POST['payment_type'] ?? '';
        $payment_type = trim($raw_payment_type);
        if (empty($payment_type) || !in_array($payment_type, ['Advance', 'Final'])) {
            $payment_type = 'Advance'; // Default to Advance for one-time bills
        }
    }
    // For Recurring bills, payment_type stays null

    // Update the bill
    $stmt = $conn->prepare("UPDATE bills 
        SET client_id=?, bill_date=?, amount=?, gst=?, total=?, description=?, project_type=?, apply_gst=?, payment_type=?, payment_mode=?, next_payment_date=?, estimated_value=?
        WHERE id=?");
    
    $stmt->bind_param("isdddssisssdi", $client_id, $bill_date, $totalAmount, $gst, $total, $description, $project_type, $apply_gst, $payment_type, $payment_mode, $next_payment, $estimated_value, $bill_id);

    if ($stmt->execute()) {
        echo "<script>alert('✅ Bill updated successfully'); location.href='bill-history.php';</script>";
    } else {
        echo "<script>alert('❌ Failed to update bill: " . $conn->error . "');</script>";
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
            max-width: 950px;
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
            display: block;
            color: #333;
        }
        input[type="text"], input[type="number"], input[type="date"], select, textarea {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border-radius: 8px;
            border: 1px solid #ccc;
            font-size: 15px;
            transition: 0.3s;
            box-sizing: border-box;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #7F00FF;
            box-shadow: 0 0 8px rgba(127, 0, 255, 0.2);
            outline: none;
        }
        textarea {
            resize: vertical;
            min-height: 80px;
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
            cursor: pointer;
        }
        .btn-primary:hover {
            transform: scale(1.02);
            opacity: 0.9;
        }
        
        /* Dynamic Items Styling */
        .item-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .item-row textarea {
            flex: 2;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
            min-height: 45px;
        }
        .item-row input {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin: 0;
        }
        .item-row button {
            background: #dc3545;
            border: none;
            color: white;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            font-size: 18px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .add-btn {
            background: linear-gradient(135deg, #7F00FF, #E100FF);
            color: white;
            border: none;
            padding: 12px;
            font-weight: bold;
            border-radius: 8px;
            width: 100%;
            margin-top: 10px;
            font-size: 16px;
            transition: 0.3s;
            cursor: pointer;
        }
        .add-btn:hover {
            transform: scale(1.02);
        }
        .total-box {
            background: #f7f7f7;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .total-box p {
            margin: 5px 0;
            font-weight: bold;
        }
        #bill-to-box {
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        #bill-to-box strong {
            display: block;
            margin-bottom: 10px;
            font-size: 16px;
            color: #333;
        }
        .form-check {
            display: flex;
            align-items: center;
            margin: 15px 0;
        }
        .form-check-input {
            width: auto !important;
            margin-right: 10px;
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
    <a href="re-nogst.php">🔁 Add Recurring No GST</a>
    <a href="one-nogst.php">🧾 One-Time No GST</a>
    <a href="bill-history.php">📊 Bill History</a>
    <a href="logout.php">🚪 Logout</a>
</div>

<div class="main-content">
    <div class="form-container">
        <h2>✏️ Edit Bill #<?= $bill['id'] ?></h2>
        <form method="post">

            <label>Estimated Project Value (Optional):</label>
            <input type="number" step="0.01" name="estimated_value" class="form-control" value="<?= htmlspecialchars($bill['estimated_value'] ?? '') ?>" oninput="updateRemaining()">

            <label>Client:</label>
            <select name="client_id" id="client_id" class="form-control mb-3" required onchange="updateBillTo()">
                <?php 
                $clients->data_seek(0); // Reset pointer
                while ($client = $clients->fetch_assoc()) {
                    $selected = $bill['client_id'] == $client['id'] ? 'selected' : '';
                    echo "<option value='{$client['id']}' data-company='{$client['company_name']}' data-address='{$client['address']}' data-gst='{$client['gst_number']}' $selected>{$client['company_name']}</option>";
                } ?>
            </select>

            <div id="bill-to-box">
                <strong>BILL TO:</strong>
                <p id="bill-company"><?= htmlspecialchars($bill['company_name']) ?></p>
                <p><strong>GST:</strong> <span id="bill-gst"><?= htmlspecialchars($bill['gst_number'] ?? '--') ?></span></p>
                <p id="bill-address"><?= nl2br(htmlspecialchars($bill['address'])) ?></p>
            </div>

            <label>Project Type:</label>
            <select name="project_type" id="projectType" class="form-control mb-3" required onchange="toggleFields()">
                <option value="One Time" <?= $bill['project_type'] == 'One Time' ? 'selected' : '' ?>>One Time</option>
                <option value="Recurring" <?= $bill['project_type'] == 'Recurring' ? 'selected' : '' ?>>Recurring</option>
            </select>

            <!-- Dynamic Items Section -->
            <label>Items & Services:</label>
            <div id="items-container">
                <?php if (!empty($existingItems)): ?>
                    <?php foreach ($existingItems as $item): ?>
                        <div class="item-row">
                            <textarea name="item_desc[]" class="form-control" placeholder="Description"><?= htmlspecialchars($item['desc']) ?></textarea>
                            <input type="number" name="item_amount[]" class="form-control" placeholder="Amount" value="<?= htmlspecialchars($item['amount']) ?>" oninput="calculateTotal()">
                            <button type="button" onclick="removeRow(this)">×</button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="item-row">
                        <textarea name="item_desc[]" class="form-control" placeholder="Description"></textarea>
                        <input type="number" name="item_amount[]" class="form-control" placeholder="Amount" oninput="calculateTotal()">
                        <button type="button" onclick="removeRow(this)">×</button>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" class="add-btn" onclick="addItem()">➕ Add New Item</button>

            <label>Bill Date:</label>
            <input type="date" name="bill_date" class="form-control mb-3" value="<?= htmlspecialchars($bill['bill_date']) ?>" required>

            <div id="gstSection">
                <label>GST (%):</label>
                <input type="number" name="gst" id="gst" step="0.01" class="form-control mb-3" value="<?= htmlspecialchars($bill['gst'] ?? '18') ?>" oninput="calculateTotal()">

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="apply_gst" id="apply_gst" <?= $bill['apply_gst'] ? 'checked' : '' ?> onchange="calculateTotal()">
                    <label class="form-check-label">Apply GST</label>
                </div>
            </div>

            <div id="paymentTypeGroup">
                <label>Payment Type:</label>
                <select name="payment_type" class="form-control mb-3">
                    <option value="Advance" <?= $bill['payment_type'] == 'Advance' ? 'selected' : '' ?>>Advance</option>
                    <option value="Final" <?= $bill['payment_type'] == 'Final' ? 'selected' : '' ?>>Final</option>
                </select>
            </div>

            <label>Payment Mode:</label>
            <select name="payment_mode" class="form-control mb-3">
                <option value="Cash" <?= $bill['payment_mode'] == 'Cash' ? 'selected' : '' ?>>Cash</option>
                <option value="UPI" <?= $bill['payment_mode'] == 'UPI' ? 'selected' : '' ?>>UPI</option>
                <option value="Cheque" <?= $bill['payment_mode'] == 'Cheque' ? 'selected' : '' ?>>Cheque</option>
                <option value="Bank Transfer" <?= $bill['payment_mode'] == 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
            </select>

            <label>Next Payment Date:</label>
            <input type="date" name="next_payment" class="form-control mb-4" value="<?= htmlspecialchars($bill['next_payment_date'] ?? '') ?>">

            <!-- Totals Section -->
            <div class="total-box">
                <p><strong>Subtotal:</strong> ₹ <span id="subtotal"><?= number_format($bill['amount'] ?? 0, 2) ?></span></p>
                <p id="gst-row"><strong>GST:</strong> ₹ <span id="gst-amount"><?= number_format(($bill['total'] ?? 0) - ($bill['amount'] ?? 0), 2) ?></span></p>
                <p><strong>Grand Total:</strong> ₹ <span id="grand-total"><?= number_format($bill['total'] ?? 0, 2) ?></span></p>
                <p id="remaining-row"><strong>Remaining Amount:</strong> ₹ <span id="remaining-amount">0.00</span></p>
            </div>

            <button type="submit" class="btn btn-primary">💾 Update Bill</button>
        </form>
    </div>
</div>

<script>
// Update Bill To information
function updateBillTo() {
    let select = document.getElementById('client_id');
    let selectedOption = select.options[select.selectedIndex];
    
    let company = selectedOption.getAttribute('data-company') || '--';
    let gst = selectedOption.getAttribute('data-gst') || '--';
    let address = selectedOption.getAttribute('data-address') || '--';
    
    document.getElementById('bill-company').textContent = company;
    document.getElementById('bill-gst').textContent = gst;
    document.getElementById('bill-address').innerHTML = address.replace(/\n/g, '<br>');
}

// Add new item row
function addItem() {
    let container = document.getElementById('items-container');
    let newRow = document.createElement('div');
    newRow.className = 'item-row';
    newRow.innerHTML = `
        <textarea name="item_desc[]" class="form-control" placeholder="Description"></textarea>
        <input type="number" name="item_amount[]" class="form-control" placeholder="Amount" oninput="calculateTotal()">
        <button type="button" onclick="removeRow(this)">×</button>
    `;
    container.appendChild(newRow);
}

// Remove item row
function removeRow(btn) {
    if (document.querySelectorAll('.item-row').length > 1) {
        btn.closest('.item-row').remove();
        calculateTotal();
    } else {
        alert('At least one item is required');
    }
}

// Calculate totals
function calculateTotal() {
    let amounts = document.querySelectorAll('input[name="item_amount[]"]');
    let subtotal = 0;
    
    amounts.forEach(input => {
        let value = parseFloat(input.value) || 0;
        subtotal += value;
    });
    
    let gstRate = parseFloat(document.getElementById('gst').value) || 0;
    let applyGst = document.getElementById('apply_gst').checked;
    let gstAmount = applyGst ? (subtotal * gstRate / 100) : 0;
    let grandTotal = subtotal + gstAmount;
    
    document.getElementById('subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('gst-amount').textContent = gstAmount.toFixed(2);
    document.getElementById('grand-total').textContent = grandTotal.toFixed(2);
    
    updateRemaining();
}

// Update remaining amount
function updateRemaining() {
    let estimated = parseFloat(document.querySelector('input[name="estimated_value"]').value) || 0;
    let grandTotal = parseFloat(document.getElementById('grand-total').textContent) || 0;
    let remaining = Math.max(estimated - grandTotal, 0);
    
    document.getElementById('remaining-amount').textContent = remaining.toFixed(2);
    
    // Show/hide remaining row based on estimated value
    let remainingRow = document.getElementById('remaining-row');
    remainingRow.style.display = estimated > 0 ? 'block' : 'none';
}

// Toggle fields based on project type
function toggleFields() {
    let projectType = document.getElementById('projectType').value;
    let paymentTypeGroup = document.getElementById('paymentTypeGroup');
    let gstSection = document.getElementById('gstSection');
    let gstRow = document.getElementById('gst-row');
    
    // Handle GST based on project type
    if (projectType === 'One Time') {
        gstSection.style.display = 'block';
        gstRow.style.display = 'block';
        paymentTypeGroup.style.display = 'block';
    } else if (projectType === 'Recurring') {
        gstSection.style.display = 'block';
        gstRow.style.display = 'block';
        paymentTypeGroup.style.display = 'none';
    }
    
    calculateTotal();
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    toggleFields();
    calculateTotal();
    updateRemaining();
    
    // Add event listeners
    document.getElementById('projectType').addEventListener('change', toggleFields);
    document.getElementById('gst').addEventListener('input', calculateTotal);
    document.getElementById('apply_gst').addEventListener('change', calculateTotal);
    document.querySelector('input[name="estimated_value"]').addEventListener('input', updateRemaining);
});
</script>

</body>
</html>
