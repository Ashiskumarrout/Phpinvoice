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
$clientName = htmlspecialchars($row['company_name']);
$clientGST = htmlspecialchars($row['gst_number'] ?? '');
$clientAddress = nl2br(htmlspecialchars($row['address']));

$totalAmount = floatval($row['amount']); // Total without GST
$gstPercent = $row['apply_gst'] ? $row['gst'] : 0;
$gstAmount = $row['apply_gst'] ? number_format(($row['amount'] * $gstPercent / 100), 2) : "0.00";
$grandTotal = number_format($row['total'], 2);
$currency = htmlspecialchars($row['currency'] ?? 'INR');
$nextPayment = $row['next_payment_date'] ? date('d/m/Y', strtotime($row['next_payment_date'])) : 'N/A';

// Clean description: remove (....) and trim spaces
$description = htmlspecialchars(trim(preg_replace('/\([^)]*\)/', '', $row['description'])));

// Background image
$bgPath = 'companylogo.jpg';
$backgroundImage = file_exists($bgPath) ? base64_encode(file_get_contents($bgPath)) : '';
$bgStyle = $backgroundImage ? "background: url('data:image/jpeg;base64,{$backgroundImage}') no-repeat center center; background-size: cover;" : '';

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
  .bill-to { margin-top: 20px; }
  .bill-to strong { font-size: 16px; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
  table.items th, table.items td { border-bottom: 2px solid red; padding: 10px; text-align: left; }
  table.summary { width: 250px; float: right; margin-top: 20px; font-size: 14px; }
  table.summary td { padding: 6px 10px; }
  .total-row { font-weight: bold; font-size: 16px; color: #000; }
  .footer-wrapper { margin-top: 350px; }
  .thank { font-size: 38px; font-weight: bold; color: #0b0b6f; margin-bottom: 10px; margin-top: 150px; }
  .footer-header { display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 5px; font-size: 14px; }
  .footer-header span { font-size: 14px; }
  .footer-header span:first-child { margin-right: 410px; }
  .footer-left { float: left; width: 50%; margin-top: 5px; font-size: 12px; }
  .footer-right { float: right; width: 45%; text-align: right; margin-top: 5px; font-size: 12px; }
  .footer-bottom { clear: both; text-align: center; font-size: 12px; margin-top: 20px; border-top: 2px solid red; padding-top: 8px; font-weight: bold; }
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
    <p><strong>INVOICE #</strong> {$invoiceNo}<br>
    <strong>INVOICE DATE:</strong> {$invoiceDate}</p>
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
    <tr>
      <td>{$description}</td>
      <td style='text-align:right;'>".number_format($totalAmount, 2)."</td>
    </tr>
  </tbody>
</table>

<table class='summary'>
  <tr><td>Subtotal:</td><td style='text-align:right;'>".number_format($totalAmount, 2)."</td></tr>
  <tr><td>GST {$gstPercent}%:</td><td style='text-align:right;'>{$gstAmount}</td></tr>
  <tr class='total-row'><td>TOTAL:</td><td style='text-align:right;'>{$grandTotal} {$currency}</td></tr>
</table>

<div class='footer-wrapper'>
  <div class='thank'>Thank You</div>
  <div class='footer-header'>
    <span>TERMS & CONDITIONS</span>
    <span>ACCOUNT DETAILS</span>
  </div>
  <div class='footer-left'>
    Payment is due within 2 days<br>
    Next Payment Date: {$nextPayment}
  </div>
  <div class='footer-right'>
    Softech18<br>
    Ac- 243305002040<br>
    IFSC-ICIC0002433<br>
    ICICI Bank<br>
    College square branch<br>
    Cuttack
  </div>
  <div class='footer-bottom'>
    Contact us: +91 9937857561 | visit us: www.softech18.com
  </div>
</div>
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
