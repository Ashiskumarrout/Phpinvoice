<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

$clients = $conn->query("SELECT id, company_name FROM clients");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = $_POST['client_id'];
    $project_type = $_POST['project_type'] === 'one_time' ? 'One Time' : 'Recurring';
    $description = '';
    $amount = 0;
    $gst = isset($_POST['gst']) ? floatval($_POST['gst']) : 0;
    $apply_gst = ($gst == 18) ? 1 : 0;
    $total = 0;
    $estimated = 0;

    // Safe bill date handling
    if ($project_type === 'One Time') {
        $bill_date = $_POST['one_time_bill_date'] ?? '';
    } else {
        $bill_date = $_POST['recurring_bill_date'] ?? '';
    }

    if (empty($bill_date)) {
        $bill_date = date('Y-m-d');
    }

    $payment_type = $_POST['payment_type'] ?? null;
    $payment_mode = $_POST['payment_mode'] ?? ($_POST['recurring_mode'] ?? null);
    $next_payment_date = $_POST['next_payment'] ?? $_POST['recurring_next_payment'] ?? null;

    if ($next_payment_date === '') {
        $next_payment_date = null;
    }

    $logoPath = 'uploads/default_logo.png';
    if (!empty($_FILES['company_logo']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fileName = time() . '_' . basename($_FILES["company_logo"]["name"]);
        $targetFile = $targetDir . $fileName;
        if (move_uploaded_file($_FILES["company_logo"]["tmp_name"], $targetFile)) {
            $logoPath = $targetFile;
        }
    }

    if ($project_type === 'One Time') {
        $description = $_POST['description'];
        $amount = floatval($_POST['amount']);
        $estimated = floatval($_POST['estimated_value']);
        $total = $amount + ($apply_gst ? ($amount * $gst / 100) : 0);
    } else {
        $description = $_POST['recurring_description'];
        $amount = floatval($_POST['recurring_amount']);
        $total = $amount + ($apply_gst ? ($amount * $gst / 100) : 0);
        $payment_type = null;
    }

    $stmt = $conn->prepare("INSERT INTO bills 
        (client_id, bill_date, amount, gst, total, description, project_type, logo, apply_gst, payment_type, payment_mode, next_payment_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        die("<div style='color:red;'>❌ Prepare failed: " . $conn->error . "</div>");
    }

    $stmt->bind_param(
        "isdddsssisss",
        $client_id,
        $bill_date,
        $amount,
        $gst,
        $total,
        $description,
        $project_type,
        $logoPath,
        $apply_gst,
        $payment_type,
        $payment_mode,
        $next_payment_date
    );

    if ($stmt->execute()) {
        echo "<script>alert('✅ Bill created successfully'); window.location.href='bill-history.php';</script>";
        exit;
    } else {
        echo "<div style='color:red; font-weight:bold;'>❌ Error saving bill: " . $stmt->error . "</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Add New Bill</title>
  <link rel="stylesheet" href="bootstrap.min.css">
  <style>
    body { margin: 0; font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; }
    .sidebar {
      width: 250px;
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      color: white; min-height: 100vh; padding: 20px; position: fixed;
    }
    .sidebar a {
      color: white; display: block; padding: 12px 0; text-decoration: none; font-weight: 500;
    }
    .sidebar a:hover {
      background: #512da8; border-radius: 6px; padding-left: 12px;
    }
    .main-content {
      margin-left: 250px; padding: 40px; width: 100%;
    }
    .form-container {
      background: white; padding: 40px; border-radius: 16px; box-shadow: 0 8px 30px rgba(0,0,0,0.1); max-width: 750px; margin: auto;
    }
    .form-group { margin-bottom: 20px; }
    label { font-weight: 600; }
    .hidden { display: none; }
  </style>
</head>
<body>
  <div class="sidebar">
    <h3>🧾 Admin Panel</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="add-client.php">➕ Add Client</a>
    <a href="client-list.php">📋 Client List</a>
    <a href="add-bill.php">🧾 Add New Bill</a>
    <a href="bill-history.php">📊 Bill History</a>
    <a href="logout.php">🚪 Logout</a>
  </div>

  <div class="main-content">
    <div class="form-container">
      <h2 class="text-center mb-4">➕ Add New Bill</h2>
      <form method="post" enctype="multipart/form-data">

        <div class="form-group">
          <label>Client</label>
          <select name="client_id" class="form-control" required>
            <option value="">Select Client</option>
            <?php while ($row = $clients->fetch_assoc()) echo "<option value='{$row['id']}'>{$row['company_name']}</option>"; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Project Type</label>
          <select id="project_type" name="project_type" class="form-control" required>
            <option value="">Select Type</option>
            <option value="one_time">One Time</option>
            <option value="recurring">Recurring</option>
          </select>
        </div>

        <div class="form-group text-center">
          <label>Company Logo (Fixed)</label><br>
          <img src="uploads/1752152956_poertfoli1.png" alt="Logo" style="max-height: 80px;">
        </div>

        <!-- One Time -->
        <div id="one_time_fields" class="hidden">
          <div class="form-group">
            <label><input type="checkbox" id="apply_gst"> Apply GST (18%)</label>
            <input type="hidden" name="gst" id="gst_input" value="0">
          </div>
          <div class="form-group">
            <label>Total Estimated Project Value</label>
            <input type="number" step="0.01" name="estimated_value" class="form-control" id="estimated">
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea name="description" class="form-control"></textarea>
          </div>
          <div class="form-group">
            <label>Payment Type</label>
            <select name="payment_type" class="form-control">
              <option>Advance</option>
              <option>Final</option>
            </select>
          </div>
          <div class="form-group">
            <label>Amount Paid</label>
            <input type="number" step="0.01" name="amount" id="paid_amount" class="form-control">
          </div>
          <div class="form-group">
            <label>Remaining Amount</label>
            <input type="text" id="remaining" class="form-control" disabled>
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
            <label>Estimate Next Payment Date</label>
            <input type="date" name="one_time_bill_date" class="form-control">
          </div>
        </div>

        <!-- Recurring -->
        <div id="recurring_fields" class="hidden">
          <div class="form-group">
            <label>Project Amount</label>
            <input type="number" step="0.01" name="recurring_amount" class="form-control" id="recurring_amount">
          </div>
          <div class="form-group">
            <label>Bill Date</label>
            <input type="date" name="recurring_bill_date" class="form-control">
          </div>
          <div class="form-group">
            <label><input type="checkbox" id="recurring_gst_check"> Apply GST (18%)</label>
            <input type="hidden" name="gst" id="recurring_gst_input" value="0">
          </div>
          <div class="form-group">
            <label>Total Amount (Project + GST)</label>
            <input type="text" id="recurring_total" class="form-control" disabled>
          </div>
          <div class="form-group">
            <label>Payment Mode</label>
            <select name="recurring_mode" class="form-control">
              <option>Cash</option>
              <option>UPI</option>
              <option>Cheque</option>
              <option>Bank Transfer</option>
            </select>
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea name="recurring_description" class="form-control"></textarea>
          </div>
          <div class="form-group">
            <label>Next Payment Date</label>
            <input type="date" name="recurring_next_payment" class="form-control">
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100">➕ Submit Bill</button>
      </form>
    </div>
  </div>

  <script>
    const projectType = document.getElementById('project_type');
    const oneTimeFields = document.getElementById('one_time_fields');
    const recurringFields = document.getElementById('recurring_fields');
    const applyGstCheckbox = document.getElementById('apply_gst');
    const gstInput = document.getElementById('gst_input');
    const recurringGstCheck = document.getElementById('recurring_gst_check');
    const recurringGstInput = document.getElementById('recurring_gst_input');
    const estimated = document.getElementById('estimated');
    const paidAmount = document.getElementById('paid_amount');
    const remaining = document.getElementById('remaining');
    const recurringAmountInput = document.getElementById('recurring_amount');
    const recurringTotal = document.getElementById('recurring_total');

    projectType.addEventListener('change', function () {
      oneTimeFields.classList.add('hidden');
      recurringFields.classList.add('hidden');
      if (this.value === 'one_time') oneTimeFields.classList.remove('hidden');
      if (this.value === 'recurring') recurringFields.classList.remove('hidden');
    });

    applyGstCheckbox.addEventListener('change', () => {
      gstInput.value = applyGstCheckbox.checked ? 18 : 0;
      updateRemaining();
    });

    recurringGstCheck.addEventListener('change', () => {
      recurringGstInput.value = recurringGstCheck.checked ? 18 : 0;
      updateRecurringTotal();
    });

    function updateRemaining() {
      const est = parseFloat(estimated?.value) || 0;
      const paid = parseFloat(paidAmount?.value) || 0;
      const gstVal = applyGstCheckbox.checked ? 18 : 0;
      const gstAmount = (est * gstVal) / 100;
      const total = est + gstAmount;
      const remain = total - paid;
      if (remaining) remaining.value = remain.toFixed(2);
    }

    function updateRecurringTotal() {
      const amount = parseFloat(recurringAmountInput?.value) || 0;
      const gstVal = recurringGstCheck.checked ? 18 : 0;
      const gstAmount = (amount * gstVal) / 100;
      const total = amount + gstAmount;
      if (recurringTotal) recurringTotal.value = total.toFixed(2);
    }

    estimated?.addEventListener('input', updateRemaining);
    paidAmount?.addEventListener('input', updateRemaining);
    recurringAmountInput?.addEventListener('input', updateRecurringTotal);
  </script>
</body>
</html>
