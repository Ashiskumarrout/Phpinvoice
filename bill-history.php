<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) header("Location: index.php");

$search = $_GET['search'] ?? '';
$search = $conn->real_escape_string($search);

if ($search) {
  $res = $conn->query("
    SELECT bills.*, clients.company_name, clients.gst_number 
    FROM bills 
    JOIN clients ON bills.client_id = clients.id 
    WHERE clients.company_name LIKE '%$search%' OR clients.gst_number LIKE '%$search%'
    ORDER BY bills.id DESC
  ");
} else {
  $res = $conn->query("
    SELECT bills.*, clients.company_name 
    FROM bills 
    JOIN clients ON bills.client_id = clients.id 
    ORDER BY bills.id DESC
  ");
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Bill History</title>
  <link rel="stylesheet" href="bootstrap.min.css">
  <style>
    body { display: flex; margin: 0; font-family: Arial; background: #f4f4f4; }
    .sidebar { width: 250px; background: linear-gradient(135deg, #7F00FF, #E100FF); min-height: 100vh; color: white; padding: 20px; position: fixed; }
    .sidebar a { color: white; display: block; padding: 10px 0; text-decoration: none; }
    .sidebar a:hover { background: #34495e; border-radius: 5px; padding-left: 10px; }
    .main-content { margin-left: 250px; padding: 40px; width: 100%; }
    table { background: white; border-radius: 8px; overflow: hidden; }
    form { margin-bottom: 20px; }
    .btn-sm { padding: 5px 10px; font-size: 14px; }
    table {
  background: white;
  border-radius: 8px;
  overflow: hidden;
  border-collapse: separate;
  border-spacing: 0 30px; /* <-- vertical spacing between rows */
}

table td, table th {
  padding: 15px;
  vertical-align: middle;
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
    <h2>📜 Bill History</h2>

    <!-- 🔍 Search -->
    <form method="get" class="d-flex gap-2">
      <input type="text" name="search" class="form-control" placeholder="Search by Client or GST" value="<?= htmlspecialchars($search) ?>">
      <button class="btn btn-dark">Search</button>
    </form>

   <table class="table table-bordered table-hover">
  <thead class="table-dark">
    <tr>
      <th>ID</th>
      <th>Client</th>
      <th>Date</th>
      <th>Base Amount</th>
      <th>GST (%)</th>
      <th>Total (₹)</th>
      <th>Description</th>
      <th>Download</th>
      <th>Edit</th>
    </tr>
  </thead>
  <tbody>
    <?php
    if ($res->num_rows > 0) {
      while ($row = $res->fetch_assoc()) {
        echo "<tr>
          <td>{$row['id']}</td>
          <td>{$row['company_name']}</td>
          <td>{$row['bill_date']}</td>
          <td>₹{$row['amount']}</td>
          <td>{$row['gst']}%</td>
          <td>₹{$row['total']}</td>
          <td>{$row['description']}</td>
          <td><a href='download_invoice.php?id={$row['id']}' class='btn btn-sm btn-success'>PDF</a></td>
          <td><a href='edit-bill.php?id={$row['id']}' class='btn btn-sm btn-warning'>Edit</a></td>
        </tr>";
      }
    } else {
      echo "<tr><td colspan='9'>No bills found.</td></tr>";
    }
    ?>
  </tbody>
</table>

  </div>
</body>
</html>
