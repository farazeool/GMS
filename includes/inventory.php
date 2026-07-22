<?php
/**
 * BrightBlaze – Inventory Item Model
 */

class InventoryItem
{
    private PDO $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
    }

    public function all(array $filters = []): array
    {
        $sql = "SELECT i.*, c.name AS category_name, s.name AS supplier_name
                FROM inventory_items i
                LEFT JOIN inventory_categories c ON c.id = i.category_id
                LEFT JOIN inventory_suppliers s ON s.id = i.supplier_id
                WHERE i.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['q'])) {
            $sql .= " AND (i.name LIKE ? OR i.sku LIKE ? OR i.barcode LIKE ?)";
            $like = '%' . $filters['q'] . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }
        if (!empty($filters['category_id'])) {
            $sql .= " AND i.category_id = ?";
            $params[] = $filters['category_id'];
        }
        if (!empty($filters['supplier_id'])) {
            $sql .= " AND i.supplier_id = ?";
            $params[] = $filters['supplier_id'];
        }
        if (!empty($filters['low_stock'])) {
            $sql .= " AND i.quantity <= i.minimum_stock";
        }
        if (!empty($filters['out_of_stock'])) {
            $sql .= " AND i.quantity <= 0";
        }
        if (!empty($filters['active_only'])) {
            $sql .= " AND i.is_active = 1";
        }

        $sql .= " ORDER BY i.name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT i.*, c.name AS category_name, s.name AS supplier_name
             FROM inventory_items i
             LEFT JOIN inventory_categories c ON c.id = i.category_id
             LEFT JOIN inventory_suppliers s ON s.id = i.supplier_id
             WHERE i.id = ? AND i.deleted_at IS NULL"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByUuid(string $uuid): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM inventory_items WHERE uuid = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$uuid]);
        return $stmt->fetch() ?: null;
    }

    public function findBySku(string $sku): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM inventory_items WHERE sku = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$sku]);
        return $stmt->fetch() ?: null;
    }

    public function findByBarcode(string $barcode): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM inventory_items WHERE barcode = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$barcode]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $fields = [
            'uuid' => uuid_generate(),
            'sku' => $data['sku'],
            'barcode' => $data['barcode'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'supplier_id' => $data['supplier_id'] ?? null,
            'purchase_price' => $data['purchase_price'] ?? 0,
            'selling_price' => $data['selling_price'] ?? 0,
            'quantity' => $data['quantity'] ?? 0,
            'minimum_stock' => $data['minimum_stock'] ?? 0,
            'reorder_level' => $data['reorder_level'] ?? 0,
            'warehouse_location' => $data['warehouse_location'] ?? null,
            'unit' => $data['unit'] ?? 'piece',
            'is_active' => $data['is_active'] ?? 1,
        ];

        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($fields)));
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));

        $stmt = $this->pdo->prepare(
            "INSERT INTO inventory_items ($cols) VALUES ($placeholders)"
        );
        $stmt->execute(array_values($fields));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $allowed = ['sku', 'barcode', 'name', 'description', 'category_id', 'supplier_id',
                    'purchase_price', 'selling_price', 'quantity', 'minimum_stock',
                    'reorder_level', 'warehouse_location', 'unit', 'is_active'];

        $set = [];
        $values = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $set[] = "`$key` = ?";
                $values[] = $value;
            }
        }

        if (empty($set)) {
            return false;
        }

        $values[] = $id;
        $stmt = $this->pdo->prepare(
            "UPDATE inventory_items SET " . implode(', ', $set) . " WHERE id = ?"
        );
        return $stmt->execute($values);
    }

    public function adjustStock(int $itemId, float $change, string $type, ?int $referenceId = null, string $referenceType = null, string $notes = null, ?int $createdBy = null): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT quantity FROM inventory_items WHERE id = ? FOR UPDATE");
            $stmt->execute([$itemId]);
            $current = $stmt->fetchColumn();

            if ($current === false) {
                $this->pdo->rollBack();
                return false;
            }

            $before = (float) $current;
            $after = $before + $change;

            if ($after < 0) {
                $this->pdo->rollBack();
                return false; // Prevent negative stock
            }

            // Update item
            $this->pdo->prepare('UPDATE inventory_items SET quantity = ?, sync_status = "pending", sync_version = sync_version + 1 WHERE id = ?')
                ->execute([$after, $itemId]);

            // Log movement
            $this->pdo->prepare(
                "INSERT INTO inventory_movements (uuid, item_id, type, quantity_change, quantity_before, quantity_after, reference_type, reference_id, notes, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                uuid_generate(),
                $itemId,
                $type,
                $change,
                $before,
                $after,
                $referenceType,
                $referenceId,
                $notes,
                $createdBy
            ]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getLowStock(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM inventory_items WHERE quantity <= minimum_stock AND is_active = 1 AND deleted_at IS NULL"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOutOfStock(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM inventory_items WHERE quantity <= 0 AND is_active = 1 AND deleted_at IS NULL"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMovementHistory(int $itemId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM inventory_movements WHERE item_id = ? ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->execute([$itemId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE inventory_items SET deleted_at = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

class InventoryCategory
{
    private PDO $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
    }

    public function all(): array
    {
        return $this->pdo->query("SELECT * FROM inventory_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(string $name, string $description = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO inventory_categories (uuid, name, description) VALUES (?, ?, ?)");
        $stmt->execute([uuid_generate(), $name, $description]);
        return (int) $this->pdo->lastInsertId();
    }
}

class InventorySupplier
{
    private PDO $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
    }

    public function all(): array
    {
        return $this->pdo->query("SELECT * FROM inventory_suppliers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO inventory_suppliers (uuid, name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            uuid_generate(),
            $data['name'],
            $data['contact_person'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}