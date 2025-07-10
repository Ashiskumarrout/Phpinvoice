<?php
require 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user'])) {
    exit('Unauthorized access');
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    exit("Invalid invoice ID.");
}

$stmt = $conn->prepare("
    SELECT bills.*, clients.company_name, clients.address 
    FROM bills 
    JOIN clients ON bills.client_id = clients.id 
    WHERE bills.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$row = $result->fetch_assoc()) {
    exit("Invoice not found.");
}

$clientName = htmlspecialchars($row['company_name']);
$clientAddress = nl2br(htmlspecialchars($row['address']));
$date = htmlspecialchars($row['bill_date']);
$description = nl2br(htmlspecialchars($row['description']));
$amount = number_format($row['amount'], 2);
$gst = number_format($row['gst'], 2);
$total = number_format($row['total'], 2);

// HTML for PDF
$html = "
<style>
  body { font-family: Arial, sans-serif; }
  h2 { text-align: center; color: #4A148C; }
  table { width: 100%; border-collapse: collapse; margin-top: 20px; }
  td, th { padding: 10px; border: 1px solid #ccc; }
  .total { font-size: 18px; font-weight: bold; text-align: right; margin-top: 20px; }
  .client-info { margin-bottom: 20px; }
  .client-info p { margin: 5px 0; }
</style>

<h2>Invoice</h2>
<div class='client-info'>
  <p><strong>Client:</strong> {$clientName}</p>
  <p><strong>Address:</strong> {$clientAddress}</p>
  <p><strong>Date:</strong> {$date}</p>
</div>
<table>
  <tr><th>Description</th><td colspan='2'>{$description}</td></tr>
  <tr><th>Base Amount</th><td colspan='2'>{$amount}</td></tr>
  <tr><th>GST (%)</th><td colspan='2'>{$gst}%</td></tr>
  <tr><th>Total Amount</th><td colspan='2'><strong>{$total}</strong></td></tr>
</table>
<p class='total'>Grand Total: {$total}</p>
";

$options = new Options();
$options->set('defaultFont', 'Arial');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("invoice_{$id}.pdf", ["Attachment" => false]);
?>
