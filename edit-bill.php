<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) header("Location: index.php");

$bill_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$clients = $conn->query("SELECT * FROM clients");

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
    $next_payment = $_POST['next_payment'] ?? null;
    $total = $amount + ($apply_gst ? ($amount * $gst / 100) : 0);

    $next_payment = !empty($next_payment) ? $next_payment : null;

    $stmt = $conn->prepare("UPDATE bills SET client_id=?, bill_date=?, amount=?, gst=?, total=?, description=?, project_type=?, apply_gst=?, payment_type=?, payment_mode=?, next_payment_date=? WHERE id=?");
    $stmt->bind_param("isdddsssissi", $client_id, $bill_date, $amount, $gst, $total, $description, $project_type, $apply_gst, $payment_type, $payment_mode, $next_payment, $bill_id);

    if ($stmt->execute()) {
        echo "<script>alert('Bill updated successfully'); location.href='bill-history.php';</script>";
    } else {
        echo "<script>alert('Failed to update bill');</script>";
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
    body { display: flex; margin: 0; font-family: Arial; background: #f4f4f4; }
    .sidebar {
      width: 250px;
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      min-height: 100vh;
      color: white;
      padding: 20px;
      position: fixed;
    }
    .sidebar a { color: white; display: block; padding: 10px 0; text-decoration: none; }
    .sidebar a:hover { background: #34495e; border-radius: 5px; padding-left: 10px; }
    .main-content { margin-left: 250px; padding: 40px; width: 100%; }
    .form-container {
      background: #fff;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      max-width: 650px;
      margin: auto;
    }
    .form-control { border-radius: 25px; padding: 12px 20px; margin-bottom: 20px; }
    .btn-primary {
      border-radius: 25px;
      padding: 10px 20px;
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      border: none;
      font-weight: bold;
    }
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
    <h2>Edit Bill</h2>
    <form method="post">
      <label>Client:</label>
      <select name="client_id" class="form-control" required>
        <?php while ($client = $clients->fetch_assoc()) {
          $selected = $bill['client_id'] == $client['id'] ? 'selected' : '';
          echo "<option value='{$client['id']}' $selected>{$client['company_name']}</option>";
        } ?>
      </select>

      <label>Project Type:</label>
      <select name="project_type" class="form-control" required>
        <option value="one_time" <?= $bill['project_type'] == 'one_time' ? 'selected' : '' ?>>One Time</option>
        <option value="recurring" <?= $bill['project_type'] == 'recurring' ? 'selected' : '' ?>>Recurring</option>
      </select>

      <label>Bill Date:</label>
      <input type="date" name="bill_date" class="form-control" value="<?= htmlspecialchars($bill['bill_date']) ?>" required>

      <label>Amount:</label>
      <input type="number" name="amount" step="0.01" class="form-control" value="<?= htmlspecialchars($bill['amount']) ?>" required>

      <label>GST (%):</label>
      <input type="number" name="gst" step="0.01" class="form-control" value="<?= htmlspecialchars($bill['gst']) ?>">

      <label><input type="checkbox" name="apply_gst" <?= $bill['apply_gst'] ? 'checked' : '' ?>> Apply GST (18%)</label>

      <label>Total:</label>
      <input type="number" name="total" step="0.01" class="form-control" value="<?= htmlspecialchars($bill['total']) ?>" required>

      <label>Description:</label>
      <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($bill['description']) ?></textarea>

      <label>Payment Type:</label>
      <select name="payment_type" class="form-control">
        <option <?= $bill['payment_type'] == 'Advance' ? 'selected' : '' ?>>Advance</option>
        <option <?= $bill['payment_type'] == 'Final' ? 'selected' : '' ?>>Final</option>
      </select>

      <label>Payment Mode:</label>
      <select name="payment_mode" class="form-control">
        <option <?= $bill['payment_mode'] == 'Cash' ? 'selected' : '' ?>>Cash</option>
        <option <?= $bill['payment_mode'] == 'UPI' ? 'selected' : '' ?>>UPI</option>
        <option <?= $bill['payment_mode'] == 'Cheque' ? 'selected' : '' ?>>Cheque</option>
        <option <?= $bill['payment_mode'] == 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
      </select>

      <label>Next Payment Date:</label>
      <input type="date" name="next_payment" class="form-control" value="<?= htmlspecialchars($bill['next_payment_date']) ?>">

      <button class="btn btn-primary w-100">Update Bill</button>
    </form>
  </div>
</div>

</body>
</html>
