<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) header("Location: index.php");

// Pagination
$limit = 50;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Filters
$search = $_GET['search'] ?? '';
$filterType = $_GET['type'] ?? '';
$clientId = $_GET['client_id'] ?? '';
$searchSafe = $conn->real_escape_string($search);

// Base Query
$query = "
  SELECT bills.*, clients.company_name, clients.gst_number 
  FROM bills 
  JOIN clients ON bills.client_id = clients.id 
  WHERE 1
";
if (!empty($searchSafe)) {
    $query .= " AND (clients.company_name LIKE '%$searchSafe%' OR clients.gst_number LIKE '%$searchSafe%')";
}
if (!empty($clientId)) {
    $query .= " AND clients.id = " . intval($clientId);
}
if ($filterType && in_array($filterType, ['onetime', 'recurring'])) {
    $query .= " AND LOWER(REPLACE(bills.project_type,' ','')) = '$filterType'";
}

// Total for Pagination
$countQuery = str_replace("SELECT bills.*, clients.company_name, clients.gst_number", "SELECT COUNT(*) as total", $query);
$totalResult = $conn->query($countQuery);
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Add Limit
$query .= " ORDER BY bills.id DESC LIMIT $limit OFFSET $offset";
$res = $conn->query($query);

// Dashboard Stats
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

body { margin: 0; font-family: 'Segoe UI', sans-serif; background: #f4f6f9; color: #333; }
.dark-mode { background: #121212; color: #e0e0e0; }
.sidebar { width: 230px; background: linear-gradient(135deg,#7F00FF,#E100FF); min-height: 100vh; color: white; padding: 20px; position: fixed; }
.sidebar a { color: white; display: block; padding: 12px 10px; text-decoration: none; font-weight: 500; border-radius: 6px; }
.sidebar a:hover { background: rgba(255,255,255,0.2); padding-left: 15px; }
.main-content { margin-left: 250px; padding: 20px; transition: 0.3s; }
.dark-mode .sidebar { background: #333; }
.stats { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 15px; margin-bottom: 20px; }
.card { background: white; padding: 15px; border-radius: 10px; text-align: center; box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
.dark-mode .card { background: #1e1e1e; color: #e0e0e0; }
.filter-bar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px; align-items:center; }
.filter-bar input, .filter-bar select { border-radius: 8px; padding: 10px; }
.filter-bar button { border-radius: 8px; padding: 10px 20px; background: linear-gradient(135deg,#7F00FF,#E100FF); border:none; color:white; font-weight: bold; }
.table-container { background:white; border-radius:8px; padding:15px; box-shadow:0 4px 10px rgba(0,0,0,0.05); overflow-x:auto; }
.dark-mode .table-container { background:#1e1e1e; }
table { width:100%; border-collapse: collapse; }
th, td { padding:12px; border-bottom:1px solid #eee; font-size:14px; text-align:center; }
thead th { position:sticky; top:0; background:#7F00FF; color:white; z-index:2; }
.tag { font-size:12px; padding:4px 8px; border-radius:4px; color:white; }
.onetime { background:#6a1b9a; }
.recurring { background:#0288d1; }
.pagination { display:flex; justify-content:center; margin-top:15px; gap:8px; }
.pagination a { padding:8px 14px; border-radius:6px; background:#eee; color:#333; text-decoration:none; font-weight:bold; }
.pagination a.active { background:linear-gradient(135deg,#7F00FF,#E100FF); color:white; }
.export-btns { margin-left:auto; display:flex; gap:10px; }
@media(max-width:768px){ .sidebar{display:none;} .main-content{margin-left:0;} }
.dark-toggle { background:#7F00FF; color:white; padding:8px 12px; border:none; border-radius:8px; cursor:pointer; margin-left:10px; }


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
    <a href="re-nogst.php">🔁 Add Recurring No GST</a>
  <a href="one-nogst.php">🧾 One-Time No GST</a>
  <a href="bill-history.php">📊 Bill History</a>
  <a href="logout.php">🚪 Logout</a>
</div>

<div class="main-content">
<h2 class="mb-3">📜 Bill History Dashboard 
  <button class="dark-toggle" onclick="toggleDarkMode()">🌙 Dark Mode</button>
</h2>

<!-- Stats -->
<div class="stats">
  <div class="card"><h4><?= $stats['total'] ?></h4><p>Total Bills</p></div>
  <div class="card"><h4><?= $stats['onetime'] ?></h4><p>One-Time Bills</p></div>
  <div class="card"><h4><?= $stats['recurring'] ?></h4><p>Recurring Bills</p></div>
  <div class="card"><h4>₹<?= number_format($stats['revenue'],2) ?></h4><p>Total Revenue</p></div>
</div>

<!-- Filters -->
<form method="get" class="filter-bar">
  <input type="text" name="search" placeholder="Search by Client or GST" value="<?= htmlspecialchars($search) ?>" class="form-control" style="max-width:250px;">
  <select name="type" class="form-control" style="max-width:180px;">
    <option value="">All Types</option>
    <option value="onetime" <?= $filterType==='onetime'?'selected':'' ?>>One-Time</option>
    <option value="recurring" <?= $filterType==='recurring'?'selected':'' ?>>Recurring</option>
  </select>
  <button type="submit">Search</button>
  <div class="export-btns">
    <a href="export.php?format=csv" class="btn btn-success btn-sm">Export CSV</a>
    <a href="export.php?format=excel" class="btn btn-info btn-sm">Export Excel</a>
  </div>
</form>

<!-- Table -->
<div class="table-container">
<table>
<thead>
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
<?php if ($res->num_rows > 0): while($row = $res->fetch_assoc()):
$tagClass = strtolower(str_replace(' ','',$row['project_type']))==='recurring'?'recurring':'onetime';
$status = ucfirst($row['status'] ?? 'Pending');
$gstDisplay = $row['apply_gst'] ? "{$row['gst']}%" : "N/A";

// ✅ Correct PDF Link Selection
if ($tagClass === 'onetime') {
    $pdfLink = ($row['apply_gst'] == 1) ? "invoice-onetime.php?id={$row['id']}" : "onenogst.php?id={$row['id']}";
} else {
    $pdfLink = ($row['apply_gst'] == 1) ? "invoice-recurring.php?id={$row['id']}" : "renogst.php?id={$row['id']}";
}

if ($tagClass==='recurring') {
    $estimated='N/A'; $remaining='N/A'; $paymentType='N/A';
} else {
    $estimatedVal = floatval($row['estimated_value']);
    $gstAmt = $row['apply_gst'] ? ($estimatedVal * floatval($row['gst']) / 100) : 0;
    $remaining = number_format(($estimatedVal + $gstAmt) - floatval($row['total']), 2);
    $estimated = "₹" . number_format($estimatedVal,2);
    $paymentType = $row['payment_type'] ?: 'N/A';
}
$nextPayment = $row['next_payment_date'] ?: 'N/A';
?>
<tr>
<td><?= $row['invoice_no'] ?: $row['id'] ?></td>
<td><a href="?client_id=<?= $row['client_id'] ?>" style="color:#7F00FF; font-weight:bold;"><?= htmlspecialchars($row['company_name']) ?></a></td>
<td><span class="tag <?= $tagClass ?>"><?= $row['project_type'] ?></span></td>
<td><?= $estimated ?></td>
<td><?= $remaining ?></td>
<td>₹<?= number_format($row['amount'],2) ?></td>
<td><?= $gstDisplay ?></td>
<td>₹<?= number_format($row['total'],2) ?></td>
<td><?= $row['currency'] ?></td>
<td><?= $status ?></td>
<td><?= $paymentType ?></td>
<td><?= $row['payment_mode'] ?></td>
<td><?= $nextPayment ?></td>
<td><?= $row['bill_date'] ?></td>
<td><?= $row['updated_at'] ?></td>
<td><a href="delete-bill.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this bill?')">Delete</a></td>
<td>
  <a href="<?= $pdfLink ?>" class="btn btn-success btn-sm" target="_blank">View PDF</a>
  <a href="<?= $pdfLink ?>" download="invoice-<?= $row['invoice_no'] ?: $row['id'] ?>.pdf" class="btn btn-primary btn-sm">Download</a>
</td>

<td><a href="edit-bill.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a></td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="18">No bills found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Pagination -->
<div class="pagination">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= $filterType ?>&client_id=<?= $clientId ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
<?php endfor; ?>
</div>
</div>

<script>
function toggleDarkMode() {
document.body.classList.toggle('dark-mode');
localStorage.setItem('darkMode', document.body.classList.contains('dark-mode') ? 'enabled' : 'disabled');
}
if (localStorage.getItem('darkMode') === 'enabled') {
document.body.classList.add('dark-mode');
}
</script>
</body>
</html>
