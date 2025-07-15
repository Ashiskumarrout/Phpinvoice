<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) header("Location: index.php");

// Handle search
$search = $_GET['search'] ?? '';
$filterType = $_GET['type'] ?? ''; // onetime, recurring, or all
$search = $conn->real_escape_string($search);

// Build query
$query = "
  SELECT bills.*, clients.company_name, clients.gst_number 
  FROM bills 
  JOIN clients ON bills.client_id = clients.id 
  WHERE 1
";

if ($search) {
  $query .= " AND (clients.company_name LIKE '%$search%' OR clients.gst_number LIKE '%$search%')";
}

if ($filterType && in_array($filterType, ['onetime', 'recurring'])) {
  $query .= " AND LOWER(REPLACE(bills.project_type,' ','')) = '$filterType'";
}

$query .= " ORDER BY bills.id DESC";
$res = $conn->query($query);

// Dashboard stats
$stats = [
  'total' => $conn->query("SELECT COUNT(*) AS c FROM bills")->fetch_assoc()['c'],
  'onetime' => $conn->query("SELECT COUNT(*) AS c FROM bills WHERE LOWER(REPLACE(project_type,' ',''))='onetime'")->fetch_assoc()['c'],
  'recurring' => $conn->query("SELECT COUNT(*) AS c FROM bills WHERE LOWER(REPLACE(project_type,' ',''))='recurring'")->fetch_assoc()['c'],
  'revenue' => $conn->query("SELECT SUM(total) AS t FROM bills")->fetch_assoc()['t'] ?? 0
];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Bill History Dashboard</title>
  <link rel="stylesheet" href="bootstrap.min.css">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { margin: 0; font-family: Arial; background: #f4f4f4; }
    .sidebar { width: 230px; background: linear-gradient(135deg,#7F00FF,#E100FF); min-height: 100vh; color: white; padding: 20px; position: fixed; }
    .sidebar a { color: white; display: block; padding: 10px 0; text-decoration: none; }
    .sidebar a:hover { background: #512da8; border-radius: 5px; padding-left: 10px; }
    .main-content { margin-left: 250px; padding: 20px; }
    .stats { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 15px; margin-bottom: 20px; }
    .card { background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .card h4 { margin: 5px 0; font-size: 22px; color: #4A148C; }
    .filter-bar { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px; }
    table { background: white; border-radius: 8px; width: 100%; overflow-x: auto; }
    th, td { padding: 10px; border: 1px solid #ddd; font-size: 14px; }
    .tag { font-size: 12px; padding: 4px 8px; border-radius: 4px; color: white; }
    .onetime { background: #6a1b9a; }
    .recurring { background: #0288d1; }
    .status { font-weight: bold; }
    @media(max-width:768px){
      .main-content { margin-left:0; }
      .sidebar { display:none; }
      table, thead, tbody, th, td, tr { display:block; }
      thead { display:none; }
      td { position:relative; padding-left:50%; border:none; border-bottom:1px solid #eee; }
      td:before { position:absolute; top:10px; left:10px; width:45%; white-space:nowrap; font-weight:bold; }
      td:nth-of-type(1):before { content: "Invoice #"; }
      td:nth-of-type(2):before { content: "Client"; }
      td:nth-of-type(3):before { content: "Project"; }
      td:nth-of-type(4):before { content: "Estimated"; }
      td:nth-of-type(5):before { content: "Remaining"; }
      td:nth-of-type(6):before { content: "Amount"; }
      td:nth-of-type(7):before { content: "GST"; }
      td:nth-of-type(8):before { content: "Total"; }
      td:nth-of-type(9):before { content: "Currency"; }
      td:nth-of-type(10):before { content: "Status"; }
      td:nth-of-type(11):before { content: "Payment Type"; }
      td:nth-of-type(12):before { content: "Mode"; }
      td:nth-of-type(13):before { content: "Next Payment"; }
      td:nth-of-type(14):before { content: "Bill Date"; }
      td:nth-of-type(15):before { content: "Updated"; }
      td:nth-of-type(16):before { content: "Delete"; }
      td:nth-of-type(17):before { content: "PDF"; }
      td:nth-of-type(18):before { content: "Edit"; }
    }
  </style>
</head>
<body>
<div class="sidebar">
  <h3>Admin Panel</h3>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="add-client.php">➕ Add Client</a>
  <a href="client-list.php">📄 Client List</a>
  <a href="add-one-time-bill.php">🧾 Add One-Time Bill</a>
  <a href="add-recurring-bill.php">🔁 Add Recurring Bill</a>
  <a href="bill-history.php">📊 Bill History</a>
  <a href="logout.php">🚪 Logout</a>
</div>

<div class="main-content">
  <h2 class="mb-3">📜 Bill History Dashboard</h2>
  <!-- Stats -->
  <div class="stats">
    <div class="card"><h4><?= $stats['total'] ?></h4><p>Total Bills</p></div>
    <div class="card"><h4><?= $stats['onetime'] ?></h4><p>One-Time Bills</p></div>
    <div class="card"><h4><?= $stats['recurring'] ?></h4><p>Recurring Bills</p></div>
    <div class="card"><h4>₹<?= number_format($stats['revenue'],2) ?></h4><p>Total Revenue</p></div>
  </div>

  <!-- Filter & Search -->
  <form method="get" class="filter-bar">
    <input type="text" name="search" placeholder="Search by Client or GST" value="<?= htmlspecialchars($search) ?>" class="form-control" style="max-width:250px;">
    <select name="type" class="form-control" style="max-width:180px;">
      <option value="">All Types</option>
      <option value="onetime" <?= $filterType==='onetime'?'selected':'' ?>>One-Time</option>
      <option value="recurring" <?= $filterType==='recurring'?'selected':'' ?>>Recurring</option>
    </select>
    <button class="btn btn-dark">Filter</button>
  </form>

  <!-- Bills Table -->
  <div class="table-responsive">
    <table class="table table-bordered">
      <thead class="table-dark">
        <tr>
          <th>Invoice #</th>
          <th>Client</th>
          <th>Project</th>
          <th>Estimated</th>
          <th>Remaining</th>
          <th>Amount</th>
          <th>GST</th>
          <th>Total</th>
          <th>Currency</th>
          <th>Status</th>
          <th>Payment Type</th>
          <th>Mode</th>
          <th>Next Payment</th>
          <th>Bill Date</th>
          <th>Updated</th>
          <th>Delete</th>
          <th>PDF</th>
          <th>Edit</th>
        </tr>
      </thead>
      <tbody>
      <?php
      if ($res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
          $gstDisplay = $row['apply_gst'] ? "{$row['gst']}%" : "N/A";
          $tagClass = (strtolower(str_replace(' ', '', $row['project_type'])) === 'recurring') ? 'recurring' : 'onetime';
          $nextPayment = $row['next_payment_date'] ?: 'N/A';
          $status = ucfirst($row['status'] ?? 'Pending');

          if ($tagClass === 'recurring') {
            $estimated = 'N/A';
            $remaining = 'N/A';
            $paymentType = 'N/A';
          } else {
            $estimatedVal = floatval($row['estimated_value']);
            $gstAmt = $row['apply_gst'] ? ($estimatedVal * floatval($row['gst']) / 100) : 0;
            $remaining = number_format(($estimatedVal + $gstAmt) - floatval($row['total']), 2);
            $estimated = "₹" . number_format($estimatedVal, 2);
            $paymentType = $row['payment_type'] ?: 'N/A';
          }

          // ✅ Correct PDF Link
          $type = strtolower(str_replace(' ', '', $row['project_type']));
          $pdfLink = ($type === 'onetime')
            ? "invoice-onetime.php?id={$row['id']}"
            : "invoice-recurring.php?id={$row['id']}";

          echo "<tr>
            <td>" . ($row['invoice_no'] ?: $row['id']) . "</td>
            <td>{$row['company_name']}</td>
            <td><span class='tag {$tagClass}'>{$row['project_type']}</span></td>
            <td>{$estimated}</td>
            <td>{$remaining}</td>
            <td>₹" . number_format($row['amount'], 2) . "</td>
            <td>{$gstDisplay}</td>
            <td>₹" . number_format($row['total'], 2) . "</td>
            <td>{$row['currency']}</td>
            <td class='status'>{$status}</td>
            <td>{$paymentType}</td>
            <td>{$row['payment_mode']}</td>
            <td>{$nextPayment}</td>
            <td>{$row['bill_date']}</td>
            <td>{$row['updated_at']}</td>
            <td><a href='delete-bill.php?id={$row['id']}' class='btn btn-sm btn-danger' onclick='return confirm(\"Delete this bill?\");'>Delete</a></td>
            <td><a href='{$pdfLink}' target='_blank' class='btn btn-sm btn-success'>PDF</a></td>
            <td><a href='edit-bill.php?id={$row['id']}' class='btn btn-sm btn-warning'>Edit</a></td>
          </tr>";
        }
      } else {
        echo "<tr><td colspan='18' class='text-center'>No bills found.</td></tr>";
      }
      ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
