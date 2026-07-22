<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/commercial.php';
require_role('admin');

$q = db()->prepare('SELECT * FROM invoices WHERE id=?');
$q->execute([(int)$_GET['id']]);
$inv = $q->fetch();
if (!$inv) { set_flash('danger', 'Invoice not found.'); header('Location: ' . base_url('invoices/index.php')); exit; }

$lines = db()->prepare('SELECT * FROM invoice_lines WHERE invoice_id=? ORDER BY sort_order')->execute([$inv['id']])->fetchAll(PDO::FETCH_ASSOC);

$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invoice ' . e($inv['invoice_number']) . '</title>
<style>body{font-family:DejaVu Sans,sans-serif;margin:0;padding:20px;} table{width:100%;border-collapse:collapse;font-size:12px;} th,td{border:1px solid #ddd;padding:6px;} th{background:#f0f0f0;} .text-end{text-align:right;} .fw-bold{font-weight:bold;} .text-muted{color:#666;} .header{text-align:center;margin-bottom:20px;} .inv-title{font-size:24px;font-weight:bold;} .section{margin:20px 0;} table{width:100%;} .text-end{text-align:right;} .fw-bold{font-weight:bold;} @media print { @page { margin: 15mm; } }</style></head><body>';
$html .= '<div class="header"><div class="inv-title">BrightBlaze Garage</div><div class="text-muted">Invoice</div></div>';
$html .= '<div class="section"><table><tr><td style="width:50%"><strong>Customer:</strong> ' . e($inv['cname'] ?? '') . '<br>' . e($inv['cphone']??'') . '<br>' . e($inv['cemail']??'') . '</td><td class="text-end"><strong>Invoice:</strong> ' . e($inv['invoice_number']) . '<br><strong>Date:</strong> ' . date('Y-m-d', strtotime($inv['created_at'])) . '<br><strong>Due:</strong> ' . e($inv['due_date'] ?? 'N/A') . '</td></tr></table></div>';
$html .= '<div class="section"><table><thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead><tbody>';
foreach ($lines = db()->prepare('SELECT * FROM invoice_lines WHERE invoice_id=? ORDER BY sort_order')->execute([$inv['id']])->fetchAll(PDO::FETCH_ASSOC) as $l) {
    $html .= '<tr><td>' . e($l['description']) . '</td><td class="text-end">' . (float)$l['quantity'] . '</td><td class="text-end">' . format_kwd($l['unit_price']) . '</td><td class="text-end">' . format_kwd($l['line_total']) . '</td></tr>';
}
$html .= '</tbody></table><table><tr><td></td><td class="text-end"><strong>Subtotal:</strong></td><td class="text-end">' . format_kwd($inv['subtotal']) . '</td></tr>';
if ((float)$inv['tax_amount'] > 0) $html .= '<tr><td></td><td class="text-end">Tax (' . (float)$inv['tax_rate'] . '%):</td><td class="text-end">' . format_kwd($inv['tax_amount']) . '</td></tr>';
$html .= '<tr><td></td><td class="text-end"><strong>Total:</strong></td><td class="text-end"><strong>' . format_kwd($inv['total']) . '</strong></td></tr>';
$html .= '<tr><td></td><td class="text-end"><strong>Paid:</strong></td><td class="text-end text-success">' . format_kwd($inv['paid_amount']) . '</td></tr>';
$html .= '<tr><td></td><td class="text-end"><strong>Balance:</strong></td><td class="text-end ' . ((float)$inv['balance'] > 0 ? 'text-danger' : '') . '"><strong>' . format_kwd($inv['balance']) . '</strong></td></tr>';
$html .= '</tbody></table>';
$html .= '</body></html>';

header('Content-Type: text/html');
echo $html;