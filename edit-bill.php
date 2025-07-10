<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) header("Location: index.php");

// Get bill ID
$bill_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch clients
$clients = $conn->query("SELECT * FROM clients");

// Handle POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_id = $_POST['client_id'];
    $bill_date = $_POST['bill_date'];
    $total = $_POST['total'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("UPDATE bills SET client_id=?, bill_date=?, total=?, description=? WHERE id=?");
    $stmt->bind_param("isdsi", $client_id, $bill_date, $total, $description, $bill_id);

    if ($stmt->execute()) {
        echo "<script>alert('Bill updated successfully'); location.href='bill-history.php';</script>";
    } else {
        echo "<script>alert('Failed to update bill');</script>";
    }
    exit;
} else {
    // Fetch the bill
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
    .sidebar a {
      color: white;
      display: block;
      padding: 10px 0;
      text-decoration: none;
    }
    .sidebar a:hover {
      background: #34495e;
      border-radius: 5px;
      padding-left: 10px;
    }
    .main-content {
      margin-left: 250px;
      padding: 40px;
      width: 100%;
    }
    .form-container {
      background: #fff;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      max-width: 600px;
      margin: auto;
    }
    .form-control {
      border-radius: 25px;
      padding: 12px 20px;
      margin-bottom: 20px;
    }
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

        <label>Bill Date:</label>
        <input type="date" name="bill_date" class="form-control" value="<?= htmlspecialchars($bill['bill_date']) ?>" required>

        <label>Total Amount:</label>
        <input type="number" name="total" step="0.01" class="form-control" value="<?= htmlspecialchars($bill['total']) ?>" required>

        <label>Description:</label>
        <textarea name="description" class="form-control" rows="4" required><?= htmlspecialchars($bill['description']) ?></textarea>

        <button class="btn btn-primary w-100">Update Bill</button>
      </form>
    </div>
  </div>

</body>
</html>
