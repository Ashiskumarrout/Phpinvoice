<?php
require 'vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

include 'db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user'])) exit('Unauthorized access');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) exit("Invalid invoice ID.");

$stmt = $conn->prepare("
    SELECT bills.*, clients.company_name, clients.gst_number, clients.address 
    FROM bills 
    JOIN clients ON bills.client_id = clients.id 
    WHERE bills.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if (!$row = $result->fetch_assoc()) exit("Invoice not found.");

// Prepare fields
$invoiceNo = htmlspecialchars($row['invoice_no'] ?: 'INV-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT));
$invoiceDate = date('d/m/Y', strtotime($row['bill_date']));
$clientName = "<strong>" . htmlspecialchars($row['company_name']) . "</strong>";  // ✅ Bold Client Name
$clientGST = htmlspecialchars($row['gst_number'] ?? '');
$clientAddress = nl2br(htmlspecialchars($row['address']));
$amount = number_format($row['amount'], 2);
$gstPercent = $row['apply_gst'] ? $row['gst'] : 0;
$gstAmount = $row['apply_gst'] ? number_format(($row['amount'] * $gstPercent / 100), 2) : "0.00";
$total = number_format($row['total'], 2);
$currency = htmlspecialchars($row['currency'] ?? 'INR');
$nextPayment = $row['next_payment_date'] ? date('d/m/Y', strtotime($row['next_payment_date'])) : 'N/A';

// Background image
$bgPath = 'companylogo.jpg';
$backgroundImage = file_exists($bgPath) ? base64_encode(file_get_contents($bgPath)) : '';
$bgStyle = $backgroundImage ? "background: url('data:image/jpeg;base64,{$backgroundImage}') no-repeat center center; background-size: cover;" : '';

// ✅ Parse multiple items from description
$rowsHtml = '';
$items = preg_split('/,(?![^\(]*\))/', $row['description']); // Split by comma, but not inside parentheses

foreach ($items as $item) {
    $item = trim($item);
    // Match description followed by amount in parentheses
    if (preg_match('/^(.+?)\s*\(([\d,.]+)\)$/', $item, $matches)) {
        $desc = htmlspecialchars(trim($matches[1]));
        $amt = number_format((float)str_replace(',', '', $matches[2]), 2);
        $rowsHtml .= "
        <tr>
            <td>{$desc}</td>
            <td style='text-align:right;'>{$amt}</td>
        </tr>";
    } else if (!empty($item)) {
        // If no amount found, show item as description only
        $desc = htmlspecialchars($item);
        $rowsHtml .= "
        <tr>
            <td>{$desc}</td>
            <td style='text-align:right;'>-</td>
        </tr>";
    }
}

// If no items were parsed, show the full description
if (empty($rowsHtml)) {
    $desc = htmlspecialchars($row['description']);
    $rowsHtml .= "
    <tr>
        <td>{$desc}</td>
        <td style='text-align:right;'>{$amount}</td>
    </tr>";
}

// Build HTML
$html = "
<meta charset='UTF-8'>
<style>
  @page { margin: 25px; }
  body { font-family: Arial, sans-serif; color: #333; padding: 20px; {$bgStyle} }
  .header { display: flex; justify-content: space-between; align-items: flex-start; }
  .company-info { font-size: 14px; }
  .company-info strong { font-size: 16px; }  /* ✅ Bold Company Name */
  .invoice-title { font-size: 30px; color: #0b0b6f; font-weight: bold; }
  .right-header { text-align: right; font-size: 14px; }
  .bill-to { margin-top: 20px; }
  .bill-to strong { font-size: 16px; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
  table.items th, table.items td { border-bottom: 2px solid red; padding: 10px; text-align: left; }
  table.summary { width: 250px; float: right; margin-top: 20px; font-size: 14px; }
  table.summary td { padding: 6px 10px; }
  .total-row { font-weight: bold; font-size: 16px; color: #000; }
</style>

<div class='header'>
  <div class='company-info'>
    <div class='invoice-title'>INVOICE</div>
    <p><strong>SOFTECH 18</strong><br>
    GST - 21BVNPR0777G1ZD<br>
    CDA SECTOR 10<br>
    CUTTACK, ODISHA<br>
    753014</p>
  </div>
  <div class='right-header'>
    <p>
        <strong>INVOICE #</strong> {$invoiceNo}<br>
        <strong>INVOICE DATE:</strong> {$invoiceDate}<br>
        <strong>NEXT PAYMENT:</strong> {$nextPayment}
    </p>
  </div>
</div>

<div class='bill-to'>
  <p><strong>BILL TO:</strong><br>
  {$clientName}<br>
  GST: {$clientGST}<br>
  {$clientAddress}</p>
</div>

<table class='items'>
  <thead>
    <tr>
      <th style='width:70%;'>DESCRIPTION</th>
      <th style='width:30%; text-align:right;'>AMOUNT</th>
    </tr>
  </thead>
  <tbody>
    {$rowsHtml}
  </tbody>
</table>

<table class='summary'>
  <tr><td>Subtotal:</td><td style='text-align:right;'>{$amount}</td></tr>
  <tr><td>GST {$gstPercent}%:</td><td style='text-align:right;'>{$gstAmount}</td></tr>
  <tr class='total-row'><td>TOTAL:</td><td style='text-align:right;'>{$total} {$currency}</td></tr>
</table>
";

// Generate PDF
$options = new Options();
$options->set('defaultFont', 'Arial');
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("invoice_{$id}.pdf", ["Attachment" => false]);
?>
