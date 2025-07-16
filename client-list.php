<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

// Search functionality
$search = $_GET['search'] ?? '';
$searchSafe = $conn->real_escape_string($search);
$query = "SELECT * FROM clients";
if (!empty($search)) {
    $query .= " WHERE company_name LIKE '%$searchSafe%' OR gst_number LIKE '%$searchSafe%' OR address LIKE '%$searchSafe%'";
}
$query .= " ORDER BY id DESC";
$res = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Client List</title>
  <link rel="stylesheet" href="bootstrap.min.css">
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
      position: fixed;
    }
    .sidebar h3 { margin-bottom: 30px; font-weight: bold; }
    .sidebar a {
      color: white;
      display: block;
      margin: 12px 0;
      text-decoration: none;
      font-weight: 500;
      padding: 10px;
      border-radius: 6px;
      transition: 0.3s;
    }
    .sidebar a:hover {
      background: rgba(255,255,255,0.2);
      padding-left: 15px;
    }

    .main {
      flex: 1;
      padding: 40px;
      margin-left: 260px;
      width: calc(100% - 260px);
    }
    .header {
      background: #fff;
      padding: 20px 30px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.08);
      border-radius: 12px;
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .header h4 {
      margin: 0;
      font-size: 18px;
      color: #333;
    }

    h2 {
      margin-top: 20px;
      font-weight: bold;
      color: #333;
    }

    .search-box {
      display: flex;
      gap: 10px;
      margin: 20px 0;
    }
    .search-box input {
      border-radius: 8px;
      padding: 12px;
      border: 1px solid #ccc;
      flex: 1;
      font-size: 14px;
    }
    .btn-primary {
      background: linear-gradient(135deg, #7F00FF, #E100FF);
      border: none;
      padding: 10px 18px;
      font-weight: bold;
      border-radius: 8px;
    }
    .btn-secondary {
      background: #6c757d;
      border: none;
      padding: 10px 18px;
      font-weight: bold;
      border-radius: 8px;
    }

    .table-wrapper {
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 6px 15px rgba(0,0,0,0.08);
    }
    .table {
      margin: 0;
      border-radius: 8px;
      overflow: hidden;
    }
    .table thead {
      background: #7F00FF;
      color: white;
    }
    .table th {
      padding: 14px;
      font-weight: 600;
    }
    .table td {
      padding: 14px;
      font-size: 14px;
      color: #333;
      vertical-align: middle;
    }
    .table tbody tr:hover {
      background: #f8f9fa;
    }
    .btn-warning {
      background-color: #ffc107;
      border: none;
      font-size: 14px;
      padding: 6px 12px;
      border-radius: 6px;
    }
    .btn-danger {
      background-color: #dc3545;
      border: none;
      font-size: 14px;
      padding: 6px 12px;
      border-radius: 6px;
    }
    @media(max-width: 768px){
      .sidebar { display: none; }
      .main { margin: 0; width: 100%; padding: 20px; }
      .search-box { flex-direction: column; }
      .search-box input, .btn-primary, .btn-secondary { width: 100%; }
    }
  </style>
</head>
<body>

<div class="sidebar">
  <h3>🧾 Admin Panel</h3>
  <a href="dashboard.php">🏠 Dashboard</a>
  <hr style="border-color: rgba(255,255,255,0.3);">
  <a href="add-client.php">➕ Add Client</a>
  <a href="client-list.php">📄 Client List</a>
  <a href="add-one-time-bill.php">🧾 Add One-Time Bill</a>
  <a href="add-recurring-bill.php">🔁 Add Recurring Bill</a>
  <a href="bill-history.php">📊 Bill History</a>
  <a href="logout.php">🚪 Logout</a>
</div>

<div class="main">
  <div class="header">
    <h4>Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?> 👋</h4>
  </div>

  <h2>📋 Client List</h2>

  <!-- Search Form -->
  <form method="get" class="search-box">
    <input type="text" name="search" placeholder="Search by Company, GST, or Address" value="<?php echo htmlspecialchars($search); ?>">
    <button type="submit" class="btn btn-primary">Search</button>
    <?php if (!empty($search)): ?>
      <a href="client-list.php" class="btn btn-secondary">Reset</a>
    <?php endif; ?>
  </form>

  <div class="table-wrapper">
    <table class="table table-bordered table-hover">
      <thead>
        <tr>
          <th>ID</th>
          <th>Company Name</th>
          <th>GST Number</th>
          <th>Address</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($res && $res->num_rows > 0) {
          while ($row = $res->fetch_assoc()) {
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>".htmlspecialchars($row['company_name'])."</td>
                    <td>".htmlspecialchars($row['gst_number'])."</td>
                    <td style='white-space: pre-line;'>".htmlspecialchars($row['address'])."</td>
                    <td>
                      <a href='edit-client.php?id={$row['id']}' class='btn btn-sm btn-warning'>Edit</a>
                      <a href='delete-client.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure you want to delete this client?\");'>Delete</a>
                    </td>
                  </tr>";
          }
        } else {
          echo "<tr><td colspan='5' class='text-center'>No clients found.</td></tr>";
        }
        ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
