<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

$clients = $conn->query("SELECT id, company_name FROM clients");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = $_POST['client_id'];
    $bill_date = $_POST['bill_date'];
    $amount = floatval($_POST['amount']);
    $gst = floatval($_POST['gst']);
    $description = $_POST['description'];
    $total = $amount + ($amount * $gst / 100);

    $stmt = $conn->prepare("INSERT INTO bills (client_id, bill_date, amount, gst, total, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isddds", $client_id, $bill_date, $amount, $gst, $total, $description);

    if ($stmt->execute()) {
        echo "<script>alert('Bill created successfully'); location.href='bill-history.php';</script>";
    } else {
        echo "<script>alert('Error adding bill');</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Add New Bill</title>
  <link href="bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background: #f8f9fa;
      display: flex;
    }
    .sidebar {
      width: 250px;
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      min-height: 100vh;
      color: white;
      padding: 20px;
      position: fixed;
    }
    .sidebar a {
      color: white;
      display: block;
      padding: 12px 0;
      text-decoration: none;
      font-weight: 500;
    }
    .sidebar a:hover {
      background: #512da8;
      border-radius: 6px;
      padding-left: 12px;
    }
    .main-content {
      margin-left: 250px;
      padding: 40px;
      width: 100%;
    }
    .form-container {
      background: white;
      padding: 40px;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
      max-width: 700px;
      margin: auto;
    }
    .form-container h2 {
      margin-bottom: 40px;
      font-weight: 600;
      color: #4A148C;
      text-align: center;
    }
    .form-group {
      margin-bottom: 30px;
    }
    .form-control {
      padding: 12px 15px;
      font-size: 16px;
      border-radius: 8px;
    }
    .form-control:focus {
      border-color: #7F00FF;
      box-shadow: 0 0 0 0.2rem rgba(127, 0, 255, 0.25);
    }
    label {
      font-weight: 600;
      margin-bottom: 8px;
      display: block;
    }
    textarea.form-control {
      height: 120px;
      resize: vertical;
    }
    .btn-primary {
      font-size: 18px;
      padding: 12px;
      border-radius: 8px;
      background-color: #7F00FF;
      border: none;
    }
    .btn-primary:hover {
      background-color: #5e00c7;
    }
  </style>
</head>
<body>
  <div class="sidebar">
    <h3 class="mb-4">Admin Panel</h3>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="add-client.php">➕ Add Client</a>
    <a href="client-list.php">📄 Client List</a>
    <a href="add-bill.php">🧾 Add New Bill</a>
    <a href="bill-history.php">📊 Bill History</a>
    <a href="logout.php">🚪 Logout</a>
  </div>

  <div class="main-content">
    <div class="form-container">
      <h2>➕ Add New Bill</h2>
      <form method="post">
        <div class="form-group">
          <label for="client_id">Client</label>
          <select name="client_id" id="client_id" class="form-control" required>
            <option value="">Select Client</option>
            <?php while ($row = $clients->fetch_assoc()) echo "<option value='{$row['id']}'>{$row['company_name']}</option>"; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="bill_date">Date</label>
          <input type="date" name="bill_date" id="bill_date" class="form-control" required />
        </div>

        <div class="form-group">
          <label for="description">Description</label>
          <textarea name="description" id="description" class="form-control" placeholder="e.g., Website development service, domain renewal, etc."></textarea>
        </div>

        <div class="form-group">
          <label for="amount">Amount (₹)</label>
          <input type="number" step="0.01" name="amount" id="amount" class="form-control" required placeholder="Enter base amount e.g. 1000.00" />
        </div>

        <div class="form-group">
          <label for="gst">GST (%)</label>
          <input type="number" step="0.01" name="gst" id="gst" class="form-control" required placeholder="e.g., 18" />
        </div>

        <div class="form-group">
          <label for="total">Total Amount (₹)</label>
          <input type="text" id="total" class="form-control bg-light" readonly placeholder="Will be calculated automatically" />
        </div>

        <button type="submit" class="btn btn-primary w-100">➕ Add Bill</button>
      </form>
    </div>
  </div>

  <script>
    const amountInput = document.getElementById('amount');
    const gstInput = document.getElementById('gst');
    const totalInput = document.getElementById('total');

    function updateTotal() {
      const amount = parseFloat(amountInput.value) || 0;
      const gst = parseFloat(gstInput.value) || 0;
      const total = amount + (amount * gst / 100);
      totalInput.value = total.toFixed(2);
    }

    amountInput.addEventListener('input', updateTotal);
    gstInput.addEventListener('input', updateTotal);
  </script>
</body>
</html>
