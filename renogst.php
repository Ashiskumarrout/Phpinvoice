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
    SELECT bills.*, clients.company_name, clients.address 
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
$clientName = "<strong>" . htmlspecialchars($row['company_name']) . "</strong>";
$clientAddress = nl2br(htmlspecialchars($row['address']));
$amount = number_format($row['amount'], 2);
$total = number_format($row['total'], 2);
$currency = htmlspecialchars($row['currency'] ?? 'INR');
$nextPayment = $row['next_payment_date'] ? date('d/m/Y', strtotime($row['next_payment_date'])) : 'N/A';

// Background image
$bgPath = 'companylogo.jpg';
$backgroundImage = file_exists($bgPath) ? base64_encode(file_get_contents($bgPath)) : '';
$bgStyle = $backgroundImage ? "background: url('data:image/jpeg;base64,{$backgroundImage}') no-repeat center center; background-size: cover;" : '';

// ✅ Parse description rows
$rowsHtml = '';
if (preg_match_all('/([a-zA-Z0-9\s]+)\s*\(([\d,.]+)\)/', $row['description'], $matches, PREG_SET_ORDER)) {
    foreach ($matches as $match) {
        $desc = htmlspecialchars(trim($match[1]));
        $amt = number_format((float)str_replace(',', '', $match[2]), 2);
        $rowsHtml .= "
        <tr>
            <td>{$desc}</td>
            <td style='text-align:right;'>{$amt}</td>
        </tr>";
    }
} else {
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
  .invoice-title { font-size: 30px; color: #0b0b6f; font-weight: bold; }
  .right-header { text-align: right; font-size: 14px; }
  .bill-to { margin-top: 20px; font-size: 14px; }
  .bill-to strong { font-size: 16px; }
  .next-payment { margin-top: 8px; font-weight: bold; color: #000; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
  table.items th, table.items td { border-bottom: 2px solid red; padding: 10px; text-align: left; }
  table.items td:last-child, table.items th:last-child { text-align: right; }
  table.summary { width: 250px; float: right; margin-top: 20px; font-size: 14px; }
  table.summary td { padding: 6px 10px; }
  .total-row { font-weight: bold; font-size: 16px; color: #000; }
</style>

<div class='header'>
  <div class='company-info'>
    <div class='invoice-title'>INVOICE</div>
    <p><strong>SOFTECH 18</strong><br>
    CDA SECTOR 10<br>
    CUTTACK, ODISHA<br>
    753014</p>
  </div>
  <div class='right-header'>
    <p><strong>INVOICE #</strong> {$invoiceNo}<br>
    <strong>DATE:</strong> {$invoiceDate}</p>
  </div>
</div>

<div class='bill-to'>
  <p><strong>BILL TO:</strong><br>
  {$clientName}<br>
  {$clientAddress}</p>
  <p class='next-payment'>Next Payment Date: {$nextPayment}</p>
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
