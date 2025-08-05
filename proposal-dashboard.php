<?php
include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) header("Location: index.php");

// Create proposals table if it doesn't exist
$createTableQuery = "
CREATE TABLE IF NOT EXISTS proposals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    client_name VARCHAR(255) NOT NULL,
    client_address TEXT,
    client_gst VARCHAR(50),
    website_details TEXT,
    website_price DECIMAL(10,2),
    social_details TEXT,
    monthly_price DECIMAL(10,2),
    quarterly_price DECIMAL(10,2),
    half_yearly_price DECIMAL(10,2),
    yearly_price DECIMAL(10,2),
    proposal_date DATE NOT NULL,
    valid_until DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'expired') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
)";
$conn->query($createTableQuery);

// Handle proposal deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    $conn->query("DELETE FROM proposals WHERE id = $deleteId");
    header("Location: proposal-dashboard.php");
    exit;
}

// Handle status update
if (isset($_POST['update_status'])) {
    $proposalId = intval($_POST['proposal_id']);
    $newStatus = $_POST['status'];
    $conn->query("UPDATE proposals SET status = '$newStatus' WHERE id = $proposalId");
    header("Location: proposal-dashboard.php");
    exit;
}

// Pagination
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Filters
$search = $_GET['search'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$clientId = $_GET['client_id'] ?? '';
$searchSafe = $conn->real_escape_string($search);

// Base Query
$query = "
  SELECT proposals.*, clients.company_name as client_company
  FROM proposals 
  LEFT JOIN clients ON proposals.client_id = clients.id 
  WHERE 1
";
if (!empty($searchSafe)) {
    $query .= " AND (proposals.client_name LIKE '%$searchSafe%' OR clients.company_name LIKE '%$searchSafe%')";
}
if (!empty($clientId)) {
    $query .= " AND proposals.client_id = " . intval($clientId);
}
if ($filterStatus && in_array($filterStatus, ['pending', 'approved', 'rejected', 'expired'])) {
    $query .= " AND proposals.status = '$filterStatus'";
}

// Total for Pagination
$countQuery = str_replace("SELECT proposals.*, clients.company_name as client_company", "SELECT COUNT(*) as total", $query);
$totalResult = $conn->query($countQuery);
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Add Limit
$query .= " ORDER BY proposals.id DESC LIMIT $limit OFFSET $offset";
$res = $conn->query($query);

// Dashboard Stats
$stats = [
  'total' => $conn->query("SELECT COUNT(*) AS c FROM proposals")->fetch_assoc()['c'],
  'pending' => $conn->query("SELECT COUNT(*) AS c FROM proposals WHERE status='pending'")->fetch_assoc()['c'],
  'approved' => $conn->query("SELECT COUNT(*) AS c FROM proposals WHERE status='approved'")->fetch_assoc()['c'],
  'rejected' => $conn->query("SELECT COUNT(*) AS c FROM proposals WHERE status='rejected'")->fetch_assoc()['c'],
  'expired' => $conn->query("SELECT COUNT(*) AS c FROM proposals WHERE status='expired' OR valid_until < CURDATE()")->fetch_assoc()['c']
];

// Auto-update expired proposals
$conn->query("UPDATE proposals SET status='expired' WHERE valid_until < CURDATE() AND status='pending'");
?>
<!DOCTYPE html>
<html>
<head>
<title>Proposal Dashboard</title>
<link rel="stylesheet" href="bootstrap.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body { margin: 0; font-family: 'Segoe UI', sans-serif; background: #f4f6f9; color: #333; }
.dark-mode { background: #121212; color: #e0e0e0; }
.sidebar { width: 230px; background: linear-gradient(135deg,#7F00FF,#E100FF); min-height: 100vh; color: white; padding: 20px; position: fixed; }
.sidebar a { color: white; display: block; padding: 12px 10px; text-decoration: none; font-weight: 500; border-radius: 6px; }
.sidebar a:hover { background: rgba(255,255,255,0.2); padding-left: 15px; }
.sidebar a.active { background: rgba(255,255,255,0.3); }
.main-content { margin-left: 250px; padding: 20px; transition: 0.3s; }
.dark-mode .sidebar { background: #333; }
.stats { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 15px; margin-bottom: 20px; }
.card { background: white; padding: 15px; border-radius: 10px; text-align: center; box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
.dark-mode .card { background: #1e1e1e; color: #e0e0e0; }
.filter-bar { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px; align-items:center; }
.filter-bar input, .filter-bar select { border-radius: 8px; padding: 10px; border: 1px solid #ddd; }
.filter-bar button { border-radius: 8px; padding: 10px 20px; background: linear-gradient(135deg,#7F00FF,#E100FF); border:none; color:white; font-weight: bold; cursor: pointer; }
.table-container { background:white; border-radius:8px; padding:15px; box-shadow:0 4px 10px rgba(0,0,0,0.05); overflow-x:auto; }
.dark-mode .table-container { background:#1e1e1e; }
table { width:100%; border-collapse: collapse; }
th, td { padding:12px; border-bottom:1px solid #eee; font-size:14px; text-align:center; }
thead th { position:sticky; top:0; background:#7F00FF; color:white; z-index:2; }
.tag { font-size:12px; padding:4px 8px; border-radius:4px; color:white; font-weight: bold; }
.pending { background:#ff9800; }
.approved { background:#4caf50; }
.rejected { background:#f44336; }
.expired { background:#607d8b; }
.pagination { display:flex; justify-content:center; margin-top:15px; gap:8px; }
.pagination a { padding:8px 14px; border-radius:6px; background:#eee; color:#333; text-decoration:none; font-weight:bold; }
.pagination a.active { background:linear-gradient(135deg,#7F00FF,#E100FF); color:white; }
.export-btns { margin-left:auto; display:flex; gap:10px; }
.btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold; border: none; cursor: pointer; }
.btn-primary { background: #007bff; color: white; }
.btn-success { background: #28a745; color: white; }
.btn-warning { background: #ffc107; color: black; }
.btn-danger { background: #dc3545; color: white; }
.btn-info { background: #17a2b8; color: white; }
.btn:hover { opacity: 0.8; }
@media(max-width:768px){ .sidebar{display:none;} .main-content{margin-left:0;} }
.dark-toggle { background:#7F00FF; color:white; padding:8px 12px; border:none; border-radius:8px; cursor:pointer; margin-left:10px; }
.proposal-actions { display: flex; gap: 5px; flex-wrap: wrap; justify-content: center; }
.status-form { display: inline-block; }
.status-select { padding: 4px; border-radius: 4px; border: 1px solid #ddd; }
</style>
</head>
<body>
<div class="sidebar">
  <h3>Admin Panel</h3>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="add-client.php">➕ Add Client</a>
  <a href="client-list.php">📄 Client List</a>
  <a href="proposalnew.php">📝 Create Proposal</a>
  <a href="proposal-dashboard.php" class="active">📊 Proposal Dashboard</a>
  <a href="add-one-time-bill.php">🧾 Add One-Time Bill</a>
  <a href="add-recurring-bill.php">🔁 Add Recurring Bill</a>
  <a href="re-nogst.php">🔁 Add Recurring No GST</a>
  <a href="one-nogst.php">🧾 One-Time No GST</a>
  <a href="bill-history.php">📜 Bill History</a>
  <a href="logout.php">🚪 Logout</a>
</div>

<div class="main-content">
<h2 class="mb-3">📋 Proposal Dashboard 
  <button class="dark-toggle" onclick="toggleDarkMode()">🌙 Dark Mode</button>
</h2>

<!-- Stats -->
<div class="stats">
  <div class="card"><h4><?= $stats['total'] ?></h4><p>Total Proposals</p></div>
  <div class="card"><h4><?= $stats['pending'] ?></h4><p>Pending</p></div>
  <div class="card"><h4><?= $stats['approved'] ?></h4><p>Approved</p></div>
  <div class="card"><h4><?= $stats['rejected'] ?></h4><p>Rejected</p></div>
  <div class="card"><h4><?= $stats['expired'] ?></h4><p>Expired</p></div>
</div>

<!-- Filters -->
<form method="get" class="filter-bar">
  <input type="text" name="search" placeholder="Search by Client Name" value="<?= htmlspecialchars($search) ?>" class="form-control" style="max-width:250px;">
  <select name="status" class="form-control" style="max-width:180px;">
    <option value="">All Status</option>
    <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>Pending</option>
    <option value="approved" <?= $filterStatus==='approved'?'selected':'' ?>>Approved</option>
    <option value="rejected" <?= $filterStatus==='rejected'?'selected':'' ?>>Rejected</option>
    <option value="expired" <?= $filterStatus==='expired'?'selected':'' ?>>Expired</option>
  </select>
  <button type="submit">🔍 Search</button>
  <div class="export-btns">
    <a href="proposalnew.php" class="btn btn-success">➕ New Proposal</a>
  </div>
</form>

<!-- Table -->
<div class="table-container">
<table>
<thead>
<tr>
<th>ID</th>
<th>Client Name</th>
<th>Services</th>
<th>Website Price</th>
<th>Social Media</th>
<th>Proposal Date</th>
<th>Valid Until</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if ($res->num_rows > 0): while($row = $res->fetch_assoc()): 
$statusClass = $row['status'];
$servicesCount = 0;
if (!empty($row['website_details']) || !empty($row['website_price'])) $servicesCount++;
if (!empty($row['social_details']) || !empty($row['monthly_price'])) $servicesCount++;

// Social media pricing display
$socialPricing = '';
if (!empty($row['monthly_price']) && $row['monthly_price'] > 0) $socialPricing .= 'M: ₹' . number_format($row['monthly_price'], 0) . ' ';
if (!empty($row['quarterly_price']) && $row['quarterly_price'] > 0) $socialPricing .= 'Q: ₹' . number_format($row['quarterly_price'], 0) . ' ';
if (!empty($row['yearly_price']) && $row['yearly_price'] > 0) $socialPricing .= 'Y: ₹' . number_format($row['yearly_price'], 0);

// Check if expired
if ($row['valid_until'] < date('Y-m-d') && $row['status'] == 'pending') {
    $statusClass = 'expired';
}
?>
<tr>
<td><?= $row['id'] ?></td>
<td><strong><?= htmlspecialchars($row['client_name']) ?></strong></td>
<td>
  <?php if (!empty($row['website_details']) || (!empty($row['website_price']) && $row['website_price'] > 0)): ?>
    <span class="tag" style="background: #7F00FF;">Website</span>
  <?php endif; ?>
  <?php if (!empty($row['social_details']) || (!empty($row['monthly_price']) && $row['monthly_price'] > 0) || (!empty($row['quarterly_price']) && $row['quarterly_price'] > 0) || (!empty($row['half_yearly_price']) && $row['half_yearly_price'] > 0) || (!empty($row['yearly_price']) && $row['yearly_price'] > 0)): ?>
    <span class="tag" style="background: #E100FF;">Social Media</span>
  <?php endif; ?>
</td>
<td><?= !empty($row['website_price']) ? '₹' . number_format($row['website_price'], 0) : '-' ?></td>
<td style="font-size: 11px;"><?= $socialPricing ?: '-' ?></td>
<td><?= date('d/m/Y', strtotime($row['proposal_date'])) ?></td>
<td><?= date('d/m/Y', strtotime($row['valid_until'])) ?></td>
<td><span class="tag <?= $statusClass ?>"><?= ucfirst($row['status']) ?></span></td>
<td>
  <div class="proposal-actions">
    <!-- Download PDF -->
    <a href="proposalnew.php?download=<?= $row['id'] ?>" class="btn btn-success" target="_blank" title="Download PDF">📄 PDF</a>
    
    <!-- Status Update -->
    <form method="post" class="status-form">
      <input type="hidden" name="proposal_id" value="<?= $row['id'] ?>">
      <select name="status" class="status-select" onchange="this.form.submit()">
        <option value="pending" <?= $row['status']=='pending'?'selected':'' ?>>Pending</option>
        <option value="approved" <?= $row['status']=='approved'?'selected':'' ?>>Approved</option>
        <option value="rejected" <?= $row['status']=='rejected'?'selected':'' ?>>Rejected</option>
      </select>
      <input type="hidden" name="update_status" value="1">
    </form>
    
    <!-- Edit -->
    <a href="proposalnew.php?edit=<?= $row['id'] ?>" class="btn btn-warning" title="Edit Proposal">✏️ Edit</a>
    
    <!-- Delete -->
    <a href="?delete=<?= $row['id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this proposal?')" title="Delete">🗑️</a>
  </div>
</td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="9">No proposals found. <a href="proposalnew.php">Create your first proposal</a></td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $filterStatus ?>&client_id=<?= $clientId ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
<?php endfor; ?>
</div>
<?php endif; ?>
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
