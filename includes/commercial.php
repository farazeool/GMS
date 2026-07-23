<?php
/**
 * BrightBlaze – Commercial Business Helpers
 * Invoices, Quotations, Payments, Notifications, Audit, Search
 */

// ---- Invoice Helpers ----

function generate_invoice_number(): string
{
    $prefix = 'INV-' . date('Y') . '-';
    $stmt = db()->prepare("SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY invoice_number DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
    // Collision check
    do {
        $num = sprintf('%s%04d', $prefix, $seq++);
        $stmt = db()->prepare('SELECT COUNT(*) FROM invoices WHERE invoice_number=?');
        $stmt->execute([$num]);
    } while ($stmt->fetchColumn() > 0);
    return $num;
}

function generate_quotation_number(): string
{
    $prefix = 'QTN-' . date('Y') . '-';
    $stmt = db()->prepare("SELECT quotation_number FROM quotations WHERE quotation_number LIKE ? ORDER BY quotation_number DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
    do {
        $num = sprintf('%s%04d', $prefix, $seq++);
        $stmt = db()->prepare('SELECT COUNT(*) FROM quotations WHERE quotation_number=?');
        $stmt->execute([$num]);
    } while ($stmt->fetchColumn() > 0);
    return $num;
}

function create_invoice_from_job(int $jobCardId, float $additionalCharges = 0, string $notes = ''): array
{
    $job = db()->prepare('SELECT jc.*, c.id AS cid, c.name AS cname, v.id AS vid, v.plate_number FROM job_cards jc JOIN customers c ON c.id=jc.customer_id JOIN vehicles v ON v.id=jc.vehicle_id WHERE jc.id=?');
    $job->execute([$jobCardId]);
    $job = $job->fetch();
    if (!$job) return ['success' => false, 'error' => 'Job not found'];

    // Get parts used
    $parts = db()->prepare('SELECT * FROM job_card_parts WHERE job_card_id=?')->execute([$jobCardId])->fetchAll(PDO::FETCH_ASSOC);

    $subtotal = (float)$additionalCharges;
    $lines = [['description' => 'Service: ' . $job['service_category'], 'quantity' => 1, 'unit_price' => 0, 'line_total' => 0]];
    foreach ($parts as $p) {
        $subtotal += (float)$p['subtotal'];
        $lines[] = ['description' => 'Part: ' . db()->prepare('SELECT name FROM inventory_items WHERE id=?')->execute([$p['item_id']])->fetchColumn(), 'quantity' => (float)$p['quantity_used'], 'unit_price' => (float)$p['sale_price'], 'line_total' => (float)$p['subtotal']];
    }

    $taxRate = 0;
    $tax = round($subtotal * $taxRate / 100, 3);
    $total = $subtotal + $tax;

    db()->beginTransaction();
    try {
        $invNum = generate_invoice_number();
        db()->prepare('INSERT INTO invoices (uuid, invoice_number, job_card_id, customer_id, vehicle_id, subtotal, tax_rate, tax_amount, total, paid_amount, balance, status, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,0,?,?,?,?)')
            ->execute([uuid_generate(), $invNum, $jobCardId, $job['cid'], $job['vid'], $subtotal, $taxRate, $tax, $total, $total, $notes, (int)($_SESSION['user_id']??0)]);
        $invId = (int)db()->lastInsertId();

        foreach ($lines as $l) {
            db()->prepare('INSERT INTO invoice_lines (uuid, invoice_id, description, quantity, unit_price, line_total) VALUES (?,?,?,?,?,?)')
                ->execute([uuid_generate(), $invId, $l['description'], $l['quantity'], $l['unit_price'], $l['line_total']]);
        }
        db()->commit();
        return ['success' => true, 'invoice_id' => $invId, 'invoice_number' => $invNum];
    } catch (Throwable $e) {
        db()->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ---- Payment Helpers ----

function record_payment(int $invoiceId, float $amount, string $method, string $ref = '', string $notes = ''): array
{
    db()->beginTransaction();
    try {
        $inv = db()->prepare('SELECT * FROM invoices WHERE id=? FOR UPDATE');
        $inv->execute([$invoiceId]);
        $inv = $inv->fetch();
        if (!$inv) { db()->rollBack(); return ['success' => false, 'error' => 'Invoice not found']; }

        $newPaid = (float)$inv['paid_amount'] + $amount;
        $newBalance = (float)$inv['total'] - $newPaid;
        $newBalance = max(0, $newBalance);
        $status = $newBalance <= 0 ? 'paid' : 'partial';
        $paidDate = $newBalance <= 0 ? date('Y-m-d H:i:s') : null;

        db()->prepare('INSERT INTO payments (uuid, invoice_id, amount, payment_method, reference_number, payment_date, notes, created_by) VALUES (?,?,?,?,?,NOW(),?,?)')
            ->execute([uuid_generate(), $invoiceId, $amount, $method, $ref ?: null, $notes ?: null, (int)($_SESSION['user_id']??0)]);

        db()->prepare('UPDATE invoices SET paid_amount=?, balance=?, status=?, paid_date=COALESCE(paid_date,?) WHERE id=?')
            ->execute([$newPaid, $newBalance, $status, $paidDate, $invoiceId]);

        db()->commit();
        return ['success' => true, 'new_balance' => $newBalance, 'status' => $status];
    } catch (Throwable $e) {
        db()->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ---- Audit Helpers ----

function audit_log(string $action, string $entityType, int $entityId, ?string $entityUuid = null, ?array $oldValues = null, ?array $newValues = null): void
{
    try {
        db()->prepare('INSERT INTO audit_log (uuid, user_id, action, entity_type, entity_id, entity_uuid, old_values, new_values, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([uuid_generate(), (int)($_SESSION['user_id']??0), $action, $entityType, $entityId, $entityUuid,
                $oldValues ? json_encode($oldValues) : null, $newValues ? json_encode($newValues) : null,
                $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
    } catch (Throwable $e) {}
}

// ---- Notification Helpers ----

function create_notification(string $type, string $channel, string $recipientType, ?int $recipientId, ?string $recipientAddress, string $subject, string $body): ?int
{
    try {
        db()->prepare('INSERT INTO notifications (uuid, type, channel, recipient_type, recipient_id, recipient_address, subject, body) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([uuid_generate(), $type, $channel, $recipientType, $recipientId, $recipientAddress, $subject, $body]);
        return (int)db()->lastInsertId();
    } catch (Throwable $e) { return null; }
}

function notify_job_assigned(int $jobCardId): void
{
    $job = db()->prepare('SELECT jc.job_number, jc.technician_id, c.name AS cname FROM job_cards jc JOIN customers c ON c.id=jc.customer_id WHERE jc.id=?');
    $job->execute([$jobCardId]); $job = $job->fetch();
    if ($job && $job['technician_id']) {
        $tech = db()->prepare('SELECT full_name, email FROM users WHERE id=?')->execute([$job['technician_id']])->fetch();
        if ($tech && $tech['email']) {
            $subject = "Job {$job['job_number']} assigned to you";
            $body = "You have been assigned job {$job['job_number']} for customer {$job['cname']}.";
            create_notification('job_assigned', 'email', 'technician', (int)$job['technician_id'], $tech['email'], $subject, $body);
        }
    }
}

function notify_job_completed(int $jobCardId): void
{
    $job = db()->prepare('SELECT jc.job_number, c.id AS cid, c.name AS cname, c.email AS cemail FROM job_cards jc JOIN customers c ON c.id=jc.customer_id WHERE jc.id=?');
    $job->execute([$jobCardId]); $job = $job->fetch();
    if ($job && $job['cemail']) {
        $subject = "Job {$job['job_number']} completed";
        $body = "Dear {$job['cname']}, your job {$job['job_number']} has been completed.";
        create_notification('job_completed', 'email', 'customer', (int)$job['cid'], $job['cemail'], $subject, $body);
    }
}

function notify_invoice_created(int $invoiceId): void
{
    $inv = db()->prepare('SELECT i.invoice_number, c.id AS cid, c.name AS cname, c.email AS cemail FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.id=?');
    $inv->execute([$invoiceId]); $inv = $inv->fetch();
    if ($inv && $inv['cemail']) {
        $subject = "Invoice {$inv['invoice_number']}";
        $body = "Dear {$inv['cname']}, invoice {$inv['invoice_number']} is ready.";
        create_notification('invoice_created', 'email', 'customer', (int)$inv['cid'], $inv['cemail'], $subject, $body);
    }
}

// ---- Service Reminders ----

function check_service_reminders(): array
{
    $reminders = db()->query("SELECT sr.*, v.plate_number, c.name AS cname, c.email AS cemail FROM service_reminders sr JOIN vehicles v ON v.id=sr.vehicle_id JOIN customers c ON c.id=v.customer_id WHERE sr.status='active' AND ((sr.next_due_date IS NOT NULL AND sr.next_due_date<=CURDATE()) OR (sr.next_due_odometer IS NOT NULL AND v.odometer >= sr.next_due_odometer))")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($reminders as $r) {
        if ($r['cemail']) {
            $subject = "Service Reminder - {$r['plate_number']}";
            $body = "Dear {$r['cname']}, your vehicle {$r['plate_number']} is due for: {$r['description']}";
            create_notification('service_reminder', 'email', 'customer', (int)$r['customer_id']??0, $r['cemail'], $subject, $body);
        }
        db()->prepare("UPDATE service_reminders SET status='triggered' WHERE id=?")->execute([$r['id']]);
    }
    return $reminders;
}

// ---- Search ----

function global_search(string $query): array
{
    $like = '%' . $query . '%';
    $results = [];

    // Search queries are wrapped in try-catch since some tables may not
    // have the deleted_at column (Stage 5 sync migrations not yet applied).
    // Without deleted_at, we search all records.
    try {
        $stmt = db()->prepare("SELECT id, name, phone, email FROM customers WHERE (name LIKE ? OR phone LIKE ? OR email LIKE ?) LIMIT 10");
        $stmt->execute([$like, $like, $like]);
        $results['customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $results['customers'] = []; }

    try {
        $stmt = db()->prepare("SELECT v.id, v.plate_number, v.make, v.model, v.vin, c.name AS customer FROM vehicles v JOIN customers c ON c.id=v.customer_id WHERE (v.plate_number LIKE ? OR v.vin LIKE ? OR v.make LIKE ? OR v.model LIKE ?) LIMIT 10");
        $stmt->execute([$like, $like, $like, $like]);
        $results['vehicles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $results['vehicles'] = []; }

    try {
        $stmt = db()->prepare("SELECT jc.id, jc.job_number, c.name AS customer, jc.status FROM job_cards jc JOIN customers c ON c.id=jc.customer_id WHERE (jc.job_number LIKE ? OR jc.service_category LIKE ?) LIMIT 10");
        $stmt->execute([$like, $like]);
        $results['job_cards'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $results['job_cards'] = []; }

    try {
        $stmt = db()->prepare("SELECT i.id, i.invoice_number, c.name AS customer, i.total, i.status FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.invoice_number LIKE ? LIMIT 10");
        $stmt->execute([$like]);
        $results['invoices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $results['invoices'] = []; }

    try {
        $stmt = db()->prepare("SELECT id, sku, name, barcode, quantity FROM inventory_items WHERE (sku LIKE ? OR name LIKE ? OR barcode LIKE ?) LIMIT 10");
        $stmt->execute([$like, $like, $like]);
        $results['inventory'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $results['inventory'] = []; }

    return $results;
}

// ---- Barcode Helper ----

function render_barcode_png(string $code, int $width = 300, int $height = 60): string
{
    $img = imagecreate($width, $height);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);
    imagefill($img, 0, 0, $white);

    // Simple Code128-like pattern - just bars of varying width
    // For production, use a library; this is a visual placeholder
    $chars = str_split($code);
    $barWidth = max(1, floor($width / (count($chars) * 3)));
    $x = 2;
    foreach ($chars as $i => $ch) {
        $bw = (ord($ch) % 3) + 1;
        $fill = $i % 2 === 0;
        for ($b = 0; $b < $bw && $x < $width; $b++) {
            imagefilledrectangle($img, $x, 2, $x + $barWidth, $height - 15, $fill ? $black : $white);
            $x += $barWidth;
        }
    }

    // Add text below
    $fontSize = 3;
    $textX = ($width - strlen($code) * imagefontwidth($fontSize)) / 2;
    imagestring($img, $fontSize, (int)$textX, $height - 14, $code, $black);

    ob_start();
    imagepng($img);
    imagedestroy($img);
    return ob_get_clean();
}

// ---- PDF Helper ----

function render_invoice_pdf(int $invoiceId): string
{
    $inv = db()->prepare('SELECT i.*, c.name AS cname, c.phone AS cphone, c.email AS cemail, c.address AS caddress, v.plate_number, v.make, v.model FROM invoices i JOIN customers c ON c.id=i.customer_id LEFT JOIN vehicles v ON v.id=i.vehicle_id WHERE i.id=?');
    $inv->execute([$invoiceId]);
    $inv = $inv->fetch();
    if (!$inv) return '';

    $lines = db()->prepare('SELECT * FROM invoice_lines WHERE invoice_id=? ORDER BY sort_order')->execute([$invoiceId])->fetchAll(PDO::FETCH_ASSOC);

    // Simple HTML-based PDF
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Invoice ' . $inv['invoice_number'] . '</title>';
    $html .= '<style>body{font-family:DejaVu Sans, sans-serif;font-size:12px;color:#222}.header{border-bottom:3px solid #e67e22;padding-bottom:10px;margin-bottom:20px}.header h1{color:#e67e22;margin:0;font-size:24px}.header .sub{color:#666;font-size:11px}table{width:100%;border-collapse:collapse;margin:15px 0}th{background:#f8f9fa;text-align:left;padding:8px;border-bottom:2px solid #dee2e6}td{padding:8px;border-bottom:1px solid #dee2e6}.total-row td{border-top:2px solid #333;font-weight:bold}.right{text-align:right}.badge{display:inline-block;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:bold}</style></head><body>';
    $html .= '<div class="header"><h1>INVOICE</h1><div class="sub">' . $inv['invoice_number'] . '</div></div>';
    $html .= '<table><tr><td width="50%"><strong>BrightBlaze Garage</strong><br>Block 1, Canada Dry Street<br>Shuwaikh Industrial, Kuwait<br>+965 2222 0000<br>info@brightblaze.com.kw</td>';
    $html .= '<td width="50%" style="text-align:right"><strong>Bill To:</strong><br>' . e($inv['cname']) . '<br>' . e($inv['cphone']) . '<br>' . e($inv['cemail'] ?? '') . '<br>' . e($inv['caddress'] ?? '');
    if ($inv['plate_number']) $html .= '<br>Vehicle: ' . e($inv['make'] . ' ' . $inv['model'] . ' (' . $inv['plate_number'] . ')');
    $html .= '</td></tr></table>';
    $html .= '<p><strong>Date:</strong> ' . date('Y-m-d') . ' &nbsp; <strong>Status:</strong> ' . $inv['status'] . ' &nbsp; <strong>Due:</strong> ' . ($inv['due_date'] ?? 'N/A') . '</p>';
    $html .= '<table><thead><tr><th>Description</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Total</th></tr></thead><tbody>';
    foreach ($lines as $l) {
        $html .= '<tr><td>' . e($l['description']) . '</td><td class="right">' . (float)$l['quantity'] . '</td><td class="right">' . number_format((float)$l['unit_price'], 3) . '</td><td class="right">' . number_format((float)$l['line_total'], 3) . '</td></tr>';
    }
    $html .= '<tr class="total-row"><td colspan="3" class="right">Subtotal</td><td class="right">' . number_format((float)$inv['subtotal'], 3) . '</td></tr>';
    if ((float)$inv['tax_amount'] > 0) $html .= '<tr><td colspan="3" class="right">Tax</td><td class="right">' . number_format((float)$inv['tax_amount'], 3) . '</td></tr>';
    $html .= '<tr class="total-row"><td colspan="3" class="right">Total</td><td class="right">' . number_format((float)$inv['total'], 3) . ' KWD</td></tr>';
    if ((float)$inv['paid_amount'] > 0) $html .= '<tr><td colspan="3" class="right">Paid</td><td class="right">' . number_format((float)$inv['paid_amount'], 3) . '</td></tr><tr><td colspan="3" class="right">Balance</td><td class="right">' . number_format((float)$inv['balance'], 3) . '</td></tr>';
    $html .= '</tbody></table>';
    if ($inv['notes']) $html .= '<p><strong>Notes:</strong> ' . e($inv['notes']) . '</p>';
    $html .= '<p style="margin-top:40px;color:#999;font-size:10px;text-align:center">Generated by BrightBlaze Garage Management System</p>';
    $html .= '</body></html>';

    // Use Dompdf if available, otherwise return HTML
    if (function_exists('exec')) {
        $file = tempnam(sys_get_temp_dir(), 'inv') . '.pdf';
        file_put_contents($file, $html);
        return $file;
    }
    return $html;
}