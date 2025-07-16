<?php
include 'db.php';

// Check if user is logged in
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit;
}

// Get format
$format = $_GET['format'] ?? 'csv';
$format = strtolower($format);

// Fetch bills with clients
$query = "
    SELECT bills.invoice_no, clients.company_name, bills.project_type, bills.amount, bills.total, bills.currency,
           bills.status, bills.payment_mode, bills.bill_date
    FROM bills
    JOIN clients ON bills.client_id = clients.id
    ORDER BY bills.id DESC
";
$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    die("No data to export.");
}

// Set filename with timestamp
$filename = "bill_export_" . date('Y-m-d_H-i-s');

// CSV Export
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename={$filename}.csv");
    $output = fopen('php://output', 'w');

    // Column headers
    fputcsv($output, ['Invoice No', 'Company', 'Project Type', 'Amount', 'Total', 'Currency', 'Status', 'Payment Mode', 'Bill Date']);

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

// Excel Export
elseif ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header("Content-Disposition: attachment; filename={$filename}.xls");

    echo "<table border='1'>";
    echo "<tr>
            <th>Invoice No</th>
            <th>Company</th>
            <th>Project Type</th>
            <th>Amount</th>
            <th>Total</th>
            <th>Currency</th>
            <th>Status</th>
            <th>Payment Mode</th>
            <th>Bill Date</th>
          </tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['invoice_no']}</td>
                <td>{$row['company_name']}</td>
                <td>{$row['project_type']}</td>
                <td>{$row['amount']}</td>
                <td>{$row['total']}</td>
                <td>{$row['currency']}</td>
                <td>{$row['status']}</td>
                <td>{$row['payment_mode']}</td>
                <td>{$row['bill_date']}</td>
              </tr>";
    }

    echo "</table>";
    exit;
}

else {
    echo "Invalid export format!";
}
?>
