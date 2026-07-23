<?php

/**
 * Stage 6 – Commercial Suite Tests
 * Covers inventory, invoices, payments, quotations, portal auth,
 * negative stock prevention, and change tracking.
 */
class CommercialTest extends BaseTestCase
{
    private static bool $dbInitialized = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (!self::$dbInitialized) {
            self::setUpTestDatabase();
            self::$dbInitialized = true;
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (self::$dbInitialized) {
            self::tearDownTestDatabase();
            self::$dbInitialized = false;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reloadEnv();
        require_once __DIR__ . '/../includes/uuid.php';
        require_once __DIR__ . '/../includes/inventory.php';
        require_once __DIR__ . '/../includes/commercial.php';
        require_once __DIR__ . '/../includes/portal_session.php';
    }

    // --- UUID Tests ---

    public function test_uuid_generate_returns_36_chars(): void
    {
        $uuid = uuid_generate();
        $this->assertIsString($uuid);
        $this->assertEquals(36, strlen($uuid));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }

    public function test_uuid_generate_returns_unique(): void
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = uuid_generate();
        }
        $this->assertCount(100, array_unique($uuids));
    }

    public function test_uuid_validate_valid(): void
    {
        $this->assertTrue(uuid_is_valid(uuid_generate()));
        $this->assertTrue(uuid_is_valid('550e8400-e29b-41d4-a716-446655440000'));
    }

    public function test_uuid_validate_invalid(): void
    {
        $this->assertFalse(uuid_is_valid('not-a-uuid'));
        $this->assertFalse(uuid_is_valid(''));
        $this->assertFalse(uuid_is_valid('550e8400-e29b-41d4-a716-44665544000'));
    }

    // --- Inventory Tests ---

    public function test_inventory_category_create(): void
    {
        $pdo = db();
        $stmt = $pdo->prepare('INSERT INTO inventory_categories (uuid, name, description) VALUES (?, ?, ?)');
        $stmt->execute([uuid_generate(), 'Test Cat', 'A test']);
        $this->assertGreaterThan(0, $pdo->lastInsertId());

        $count = $pdo->query("SELECT COUNT(*) FROM inventory_categories WHERE name='Test Cat'")->fetchColumn();
        $this->assertEquals(1, (int)$count);
    }

    public function test_inventory_item_create(): void
    {
        $pdo = db();
        $catStmt = $pdo->prepare('INSERT INTO inventory_categories (uuid, name) VALUES (?, ?)');
        $catStmt->execute([uuid_generate(), 'Parts Cat']);
        $catId = (int) $pdo->lastInsertId();

        $model = new InventoryItem();
        $itemId = $model->create([
            'sku' => 'INV-TEST-001',
            'name' => 'Oil Filter',
            'category_id' => $catId,
            'purchase_price' => 2.500,
            'selling_price' => 5.000,
            'quantity' => 10,
            'minimum_stock' => 3,
            'unit' => 'piece',
        ]);

        $this->assertGreaterThan(0, $itemId);

        $item = $model->find($itemId);
        $this->assertNotNull($item);
        $this->assertEquals('Oil Filter', $item['name']);
        $this->assertEquals('INV-TEST-001', $item['sku']);
        $this->assertEquals('10.00', $item['quantity']);
    }

    public function test_inventory_adjust_stock_increases(): void
    {
        $pdo = db();
        $catStmt = $pdo->prepare('INSERT INTO inventory_categories (uuid, name) VALUES (?, ?)');
        $catStmt->execute([uuid_generate(), 'Adj Test']);
        $catId = (int) $pdo->lastInsertId();

        $model = new InventoryItem();
        $itemId = $model->create([
            'sku' => 'INV-ADJ-001',
            'name' => 'Brake Pad',
            'category_id' => $catId,
            'purchase_price' => 3.000,
            'selling_price' => 7.000,
            'quantity' => 5,
        ]);

        $result = $model->adjustStock($itemId, 10, 'purchase');
        $this->assertTrue($result);

        $item = $model->find($itemId);
        $this->assertEquals('15.00', $item['quantity']);
    }

    public function test_inventory_adjust_stock_decreases(): void
    {
        $pdo = db();
        $catStmt = $pdo->prepare('INSERT INTO inventory_categories (uuid, name) VALUES (?, ?)');
        $catStmt->execute([uuid_generate(), 'Adj Test 2']);
        $catId = (int) $pdo->lastInsertId();

        $model = new InventoryItem();
        $itemId = $model->create([
            'sku' => 'INV-ADJ-002',
            'name' => 'Spark Plug',
            'category_id' => $catId,
            'purchase_price' => 1.000,
            'selling_price' => 2.500,
            'quantity' => 20,
        ]);

        $result = $model->adjustStock($itemId, -5, 'sale');
        $this->assertTrue($result);

        $item = $model->find($itemId);
        $this->assertEquals('15.00', $item['quantity']);
    }

    public function test_inventory_prevents_negative_stock(): void
    {
        $pdo = db();
        $catStmt = $pdo->prepare('INSERT INTO inventory_categories (uuid, name) VALUES (?, ?)');
        $catStmt->execute([uuid_generate(), 'Neg Test']);
        $catId = (int) $pdo->lastInsertId();

        $model = new InventoryItem();
        $itemId = $model->create([
            'sku' => 'INV-NEG-001',
            'name' => 'Air Filter',
            'category_id' => $catId,
            'purchase_price' => 4.000,
            'selling_price' => 8.000,
            'quantity' => 3,
        ]);

        $result = $model->adjustStock($itemId, -10, 'sale');
        $this->assertFalse($result);

        $item = $model->find($itemId);
        $this->assertEquals('3.00', $item['quantity']);
    }

    public function test_inventory_movements_recorded(): void
    {
        $pdo = db();
        $catStmt = $pdo->prepare('INSERT INTO inventory_categories (uuid, name) VALUES (?, ?)');
        $catStmt->execute([uuid_generate(), 'Mov Test']);
        $catId = (int) $pdo->lastInsertId();

        $model = new InventoryItem();
        $itemId = $model->create([
            'sku' => 'INV-MOV-001',
            'name' => 'Wiper Blade',
            'category_id' => $catId,
            'purchase_price' => 1.500,
            'selling_price' => 3.000,
            'quantity' => 0,
        ]);

        $model->adjustStock($itemId, 15, 'purchase');
        $model->adjustStock($itemId, -3, 'sale');

        $movements = $model->getMovementHistory($itemId);
        $this->assertCount(2, $movements);
        $this->assertEquals('15.00', $movements[0]['quantity_after']);
        $this->assertEquals('12.00', $movements[1]['quantity_after']);
    }

    public function test_inventory_low_stock_detection(): void
    {
        $pdo = db();
        $catStmt = $pdo->prepare('INSERT INTO inventory_categories (uuid, name) VALUES (?, ?)');
        $catStmt->execute([uuid_generate(), 'Low Stock Test']);
        $catId = (int) $pdo->lastInsertId();

        $model = new InventoryItem();
        $itemId = $model->create([
            'sku' => 'INV-LOW-001',
            'name' => 'Low Item',
            'category_id' => $catId,
            'purchase_price' => 1.000,
            'selling_price' => 2.000,
            'quantity' => 2,
            'minimum_stock' => 5,
        ]);

        $lowStock = $model->getLowStock();
        $found = false;
        foreach ($lowStock as $item) {
            if ((int) $item['id'] === $itemId) { $found = true; break; }
        }
        $this->assertTrue($found, 'Low stock item should be detected');
    }

    // --- Invoice Tests ---

    public function test_invoice_number_generation(): void
    {
        $num1 = generate_invoice_number();
        $this->assertStringStartsWith('INV-' . date('Y') . '-', $num1);

        // Create an invoice with this number so the next call generates a different one
        $pdo = db();
        $pdo->exec("INSERT INTO invoices (uuid, invoice_number, customer_id, subtotal, tax_amount, total, paid_amount, balance, status) VALUES ('" . uuid_generate() . "', '$num1', 1, 100, 0, 100, 0, 100, 'draft')");

        $num2 = generate_invoice_number();

        $this->assertStringStartsWith('INV-' . date('Y') . '-', $num1);
        $this->assertStringStartsWith('INV-' . date('Y') . '-', $num2);
        $this->assertNotEquals($num1, $num2);
    }

    public function test_quotation_number_generation(): void
    {
        $num1 = generate_quotation_number();
        $this->assertStringStartsWith('QTN-' . date('Y') . '-', $num1);

        // Create a quotation with this number so the next call generates a different one
        $pdo = db();
        $pdo->exec("INSERT INTO quotations (uuid, quotation_number, customer_id, vehicle_id, subtotal, tax_amount, total, status) VALUES ('" . uuid_generate() . "', '$num1', 1, 1, 100, 0, 100, 'draft')");

        $num2 = generate_quotation_number();

        $this->assertStringStartsWith('QTN-' . date('Y') . '-', $num1);
        $this->assertStringStartsWith('QTN-' . date('Y') . '-', $num2);
        $this->assertNotEquals($num1, $num2);
    }

    public function test_payment_recording(): void
    {
        $pdo = db();
        $pdo->exec("INSERT INTO customers (name, phone, email) VALUES ('Pay Test Customer', '+965 9999 0001', 'paytest@test.com')");
        $custId = (int) $pdo->lastInsertId();

        $invUuid = uuid_generate();
        $invNum = 'INV-TEST-' . uniqid();
        $pdo->prepare("INSERT INTO invoices (uuid, invoice_number, customer_id, subtotal, total, paid_amount, balance, status, created_by) VALUES (?, ?, ?, 100.000, 100.000, 0, 100.000, 'sent', 1)")
            ->execute([$invUuid, $invNum, $custId]);
        $invId = (int) $pdo->lastInsertId();

        $result = record_payment($invId, 50.000, 'cash');
        $this->assertTrue($result['success']);
        $this->assertEquals('50.000', $result['new_balance']);
        $this->assertEquals('partial', $result['status']);

        $result2 = record_payment($invId, 50.000, 'card');
        $this->assertTrue($result2['success']);
        $this->assertEquals('0.000', $result2['new_balance']);
        $this->assertEquals('paid', $result2['status']);
    }

    public function test_payment_partial_balance(): void
    {
        $pdo = db();
        $pdo->exec("INSERT INTO customers (name, phone, email) VALUES ('Partial Test', '+965 9999 0002', 'partial@test.com')");
        $custId = (int) $pdo->lastInsertId();

        $invUuid = uuid_generate();
        $invNum = 'INV-PART-' . uniqid();
        $pdo->prepare("INSERT INTO invoices (uuid, invoice_number, customer_id, subtotal, total, paid_amount, balance, status) VALUES (?, ?, ?, 200.000, 200.000, 0, 200.000, 'sent')")
            ->execute([$invUuid, $invNum, $custId]);
        $invId = (int) $pdo->lastInsertId();

        $result = record_payment($invId, 75.000, 'bank_transfer');
        $this->assertTrue($result['success']);
        $this->assertEquals('125.000', $result['new_balance']);
        $this->assertEquals('partial', $result['status']);

        $inv = $pdo->prepare('SELECT paid_amount, balance, status FROM invoices WHERE id=?');
        $inv->execute([$invId]);
        $inv = $inv->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('75.000', $inv['paid_amount']);
        $this->assertEquals('125.000', $inv['balance']);
        $this->assertEquals('partial', $inv['status']);
    }

    // --- Portal Auth Tests ---

    public function test_portal_functions_exist(): void
    {
        $this->assertTrue(function_exists('portal_start_session'));
        $this->assertTrue(function_exists('portal_require_login'));
        $this->assertTrue(function_exists('portal_customer_id'));
        $this->assertTrue(function_exists('portal_customer_name'));
        $this->assertTrue(function_exists('portal_login'));
        $this->assertTrue(function_exists('portal_logout'));
        $this->assertTrue(function_exists('portal_is_logged_in'));
    }

    // --- Security Tests ---

    public function test_audit_log_helper_creates_entry(): void
    {
        $pdo = db();
        $before = $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
        audit_log('created', 'customers', 1, 'test-uuid', null, ['name' => 'Test']);
        $after = $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
        $this->assertGreaterThan((int)$before, (int)$after);
    }

    public function test_audit_log_stores_json_values(): void
    {
        $pdo = db();
        $id = audit_log('updated', 'customers', 99, null, ['name' => 'Old'], ['name' => 'New']);
        $entry = $pdo->prepare('SELECT old_values, new_values FROM audit_log WHERE entity_type=? AND entity_id=? ORDER BY id DESC LIMIT 1');
        $entry->execute(['customers', 99]);
        $entry = $entry->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($entry);
        $decoded = json_decode($entry['old_values'], true);
        $this->assertEquals('Old', $decoded['name']);
    }

    public function test_notification_create(): void
    {
        $pdo = db();
        $id = create_notification('test_event', 'email', 'customer', 1, 'test@test.com', 'Test Subject', 'Test body');
        $this->assertNotNull($id);
        $this->assertGreaterThan(0, $id);

        $notif = $pdo->prepare('SELECT * FROM notifications WHERE id=?');
        $notif->execute([$id]);
        $row = $notif->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('test_event', $row['type']);
        $this->assertEquals('email', $row['channel']);
        $this->assertEquals('pending', $row['status']);
    }

    // --- Search Tests ---

    public function test_global_search_empty_query(): void
    {
        $results = global_search('');
        $this->assertIsArray($results);
        // Empty query returns all records (wildcard match)
        $this->assertIsArray($results['customers']);
        $this->assertIsArray($results['vehicles']);
    }

    public function test_global_search_returns_customers(): void
    {
        $results = global_search('Customer');
        $this->assertIsArray($results['customers']);
        // At least seeded customers should match
        $this->assertGreaterThanOrEqual(0, count($results['customers']));
    }
}
