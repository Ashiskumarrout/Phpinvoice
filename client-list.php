<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Client List</title>
  <link rel="stylesheet" href="bootstrap.min.css">
  <link rel="stylesheet" href="style.css">
  <style>
    body {
      display: flex;
      min-height: 100vh;
      margin: 0;
      background: #f4f6f9;
      font-family: 'Segoe UI', sans-serif;
    }
    .sidebar {
      width: 250px;
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      color: white;
      padding: 20px;
      height: 100vh;
    }
    .sidebar h3 {
      margin-bottom: 30px;
    }
    .sidebar a {
      color: white;
      display: block;
      margin: 10px 0;
      text-decoration: none;
      font-weight: 500;
    }
    .sidebar a:hover {
      text-decoration: underline;
    }
    .main {
      flex: 1;
      padding: 30px;
    }
    .main h2 {
      margin-top: 20px;
      font-weight: bold;
    }
    .table {
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .table th, .table td {
      vertical-align: middle;
      text-align: center;
      padding: 12px 15px;
    }
    .header {
      background: white;
      padding: 15px 30px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-radius: 8px;
    }
  </style>
</head>
<body>

<div class="sidebar">
  <h3>Admin Panel</h3>
  <a href="dashboard.php">← Back to Dashboard</a>
  <hr style="border-color: rgba(255,255,255,0.3);">
  <a href="add-client.php">➕ Add Client</a>
  <a href="client-list.php">📄 Client List</a>
  <a href="add-bill.php">🧾 Add New Bill</a>
  <a href="bill-history.php">📜 Bill History</a>
  <a href="logout.php">🚪 Logout</a>
</div>

<div class="main">
  <div class="header">
    <h4>Welcome, <?php echo $_SESSION['user']; ?> 👋</h4>
  </div>

  <h2>📋 Client List</h2>
  <table class="table table-bordered table-hover mt-3">
    <thead class="table-dark">
      <tr>
        <th>ID</th>
        <th>Company Name</th>
        <th>GST Number</th>
        <th>Address</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $res = $conn->query("SELECT * FROM clients");
      if ($res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
          echo "<tr>
                  <td>{$row['id']}</td>
                  <td>{$row['company_name']}</td>
                  <td>{$row['gst_number']}</td>
                  <td style='white-space: pre-line;'>{$row['address']}</td>
                </tr>";
        }
      } else {
        echo "<tr><td colspan='4'>No clients found.</td></tr>";
      }
      ?>
    </tbody>
  </table>
</div>

</body>
</html>
