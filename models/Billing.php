<?php
class Billing {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getPdo() { return $this->pdo; }

    /** Auto-migrate ENUM columns to support new payment methods */
    public function runPaymentMethodsMigration(): void {
        $enum = "ENUM('cash','momo','bank_transfer','visa','zalopay','vnpay')";
        try {
            $this->pdo->exec("ALTER TABLE ticket_billing MODIFY COLUMN payment_method {$enum} DEFAULT NULL");
        } catch (\PDOException $e) { /* already up to date */ }
        try {
            $this->pdo->exec("ALTER TABLE ticket_payments MODIFY COLUMN method {$enum} NOT NULL");
        } catch (\PDOException $e) { /* already up to date */ }
    }

    // ── Service Templates ──────────────────────────────────────────────

    public function getTemplatesByCategory(int $categoryId): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM service_templates WHERE category_id = ? AND is_active = 1 ORDER BY sort_order, name"
        );
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll();
    }

    public function getAllTemplates(): array {
        $stmt = $this->pdo->query(
            "SELECT st.*, c.name AS category_name
             FROM service_templates st
             JOIN categories c ON c.id = st.category_id
             ORDER BY c.name, st.sort_order, st.name"
        );
        return $stmt->fetchAll();
    }

    public function getTemplate(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM service_templates WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function createTemplate(array $d): bool {
        $stmt = $this->pdo->prepare(
            "INSERT INTO service_templates (category_id, name, description, price, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            (int)$d['category_id'],
            trim($d['name']),
            trim($d['description'] ?? ''),
            (int)$d['price'],
            isset($d['is_active']) ? 1 : 0,
            (int)($d['sort_order'] ?? 0),
        ]);
    }

    public function updateTemplate(int $id, array $d): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE service_templates SET category_id=?, name=?, description=?, price=?, is_active=?, sort_order=?
             WHERE id=?"
        );
        return $stmt->execute([
            (int)$d['category_id'],
            trim($d['name']),
            trim($d['description'] ?? ''),
            (int)$d['price'],
            isset($d['is_active']) ? 1 : 0,
            (int)($d['sort_order'] ?? 0),
            $id,
        ]);
    }

    public function deleteTemplate(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM service_templates WHERE id=?");
        return $stmt->execute([$id]);
    }

    // ── Ticket Billing ─────────────────────────────────────────────────

    public function getBillingByTicket(int $ticketId): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT tb.*, st.name AS template_name
             FROM ticket_billing tb
             LEFT JOIN service_templates st ON st.id = tb.service_template_id
             WHERE tb.ticket_id = ?"
        );
        $stmt->execute([$ticketId]);
        return $stmt->fetch() ?: null;
    }

    public function createBilling(array $d): bool {
        $stmt = $this->pdo->prepare(
            "INSERT INTO ticket_billing (ticket_id, service_template_id, price, note, created_by)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               service_template_id = VALUES(service_template_id),
               price = VALUES(price),
               note  = VALUES(note),
               updated_at = CURRENT_TIMESTAMP"
        );
        return $stmt->execute([
            (int)$d['ticket_id'],
            $d['service_template_id'] ? (int)$d['service_template_id'] : null,
            (int)$d['price'],
            trim($d['note'] ?? ''),
            (int)$d['created_by'],
        ]);
    }

    public function updateBillingStatus(int $billingId, string $status, ?string $method = null): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE ticket_billing SET payment_status=?, payment_method=?, updated_at=NOW() WHERE id=?"
        );
        return $stmt->execute([$status, $method, $billingId]);
    }

    public function getAllBilling(int $limit = 200, int $offset = 0): array {
        $limit  = max(1, (int)$limit);
        $offset = max(0, (int)$offset);
        $stmt = $this->pdo->query(
            "SELECT tb.*, t.title AS ticket_title, t.id AS ticket_id,
                    u.fullname AS customer_name, st.name AS template_name
             FROM ticket_billing tb
             JOIN tickets t ON t.id = tb.ticket_id
             LEFT JOIN users u ON u.id = t.created_by
             LEFT JOIN service_templates st ON st.id = tb.service_template_id
             ORDER BY tb.updated_at DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        return $stmt->fetchAll();
    }

    // ── Payments ───────────────────────────────────────────────────────

    public function createPayment(array $d): int {
        // If method ENUM doesn't include the value yet, fall back to storing as 'cash' equivalent
        // by catching the DB error and providing a clear message
        $stmt = $this->pdo->prepare(
            "INSERT INTO ticket_payments (ticket_id, billing_id, method, amount, status, momo_ref, created_by)
             VALUES (?, ?, ?, ?, 'pending', ?, ?)"
        );
        $stmt->execute([
            (int)$d['ticket_id'],
            (int)$d['billing_id'],
            $d['method'],
            (int)$d['amount'],
            $d['momo_ref'] ?? null,
            (int)$d['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function confirmPayment(int $paymentId, int $confirmedBy): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE ticket_payments SET status='confirmed', confirmed_by=?, confirmed_at=NOW() WHERE id=? AND status='pending'"
        );
        return $stmt->execute([$confirmedBy, $paymentId]) && $stmt->rowCount() > 0;
    }

    public function cancelPayment(int $paymentId): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE ticket_payments SET status='cancelled' WHERE id=? AND status='pending'"
        );
        return $stmt->execute([$paymentId]);
    }

    public function getPayment(int $paymentId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM ticket_payments WHERE id=?");
        $stmt->execute([$paymentId]);
        return $stmt->fetch() ?: null;
    }

    public function getPaymentsByTicket(int $ticketId): array {
        $stmt = $this->pdo->prepare(
            "SELECT tp.*, u.fullname AS confirmed_by_name
             FROM ticket_payments tp
             LEFT JOIN users u ON u.id = tp.confirmed_by
             WHERE tp.ticket_id = ?
             ORDER BY tp.created_at DESC"
        );
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    // ── Revenue Stats ──────────────────────────────────────────────────

    public function getRevenueToday(): int {
        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(amount),0) FROM ticket_payments
             WHERE status='confirmed' AND DATE(confirmed_at)=CURDATE()"
        );
        return (int)$stmt->fetchColumn();
    }

    public function getRevenueThisMonth(): int {
        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(amount),0) FROM ticket_payments
             WHERE status='confirmed'
               AND YEAR(confirmed_at)=YEAR(NOW()) AND MONTH(confirmed_at)=MONTH(NOW())"
        );
        return (int)$stmt->fetchColumn();
    }

    public function getRevenueThisYear(): int {
        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(amount),0) FROM ticket_payments
             WHERE status='confirmed' AND YEAR(confirmed_at)=YEAR(NOW())"
        );
        return (int)$stmt->fetchColumn();
    }

    public function getRevenueByMonth(string $year): array {
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(confirmed_at,'%Y-%m') AS month,
                    COALESCE(SUM(amount),0) AS revenue
             FROM ticket_payments
             WHERE status='confirmed' AND YEAR(confirmed_at)=?
             GROUP BY month ORDER BY month ASC"
        );
        $stmt->execute([$year]);
        return $stmt->fetchAll();
    }

    public function getRevenueByYear(int $count = 3): array {
        $stmt = $this->pdo->prepare(
            "SELECT YEAR(confirmed_at) AS year,
                    COALESCE(SUM(amount),0) AS revenue
             FROM ticket_payments
             WHERE status='confirmed' AND YEAR(confirmed_at) >= YEAR(NOW()) - ?
             GROUP BY year ORDER BY year ASC"
        );
        $stmt->execute([$count - 1]);
        return $stmt->fetchAll();
    }

    public function getRevenueByDay(string $year, string $month): array {
        $stmt = $this->pdo->prepare(
            "SELECT DATE(confirmed_at) AS day, COALESCE(SUM(amount),0) AS revenue
             FROM ticket_payments
             WHERE status='confirmed' AND YEAR(confirmed_at)=? AND MONTH(confirmed_at)=?
             GROUP BY day ORDER BY day ASC"
        );
        $stmt->execute([$year, $month]);
        return $stmt->fetchAll();
    }

    public function getRevenueComparison(string $year1, string $year2): array {
        $stmt = $this->pdo->prepare(
            "SELECT m.month,
                    COALESCE(SUM(CASE WHEN YEAR(tp.confirmed_at)=? THEN tp.amount END),0) AS rev1,
                    COALESCE(SUM(CASE WHEN YEAR(tp.confirmed_at)=? THEN tp.amount END),0) AS rev2
             FROM (SELECT 1 AS month UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                   UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8
                   UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12) m
             LEFT JOIN ticket_payments tp
               ON MONTH(tp.confirmed_at)=m.month
              AND YEAR(tp.confirmed_at) IN (?,?)
              AND tp.status='confirmed'
             GROUP BY m.month ORDER BY m.month ASC"
        );
        $stmt->execute([$year1, $year2, $year1, $year2]);
        return $stmt->fetchAll();
    }

    public function getUnpaidCount(): int {
        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM ticket_billing WHERE payment_status='unpaid' AND price > 0"
        );
        return (int)$stmt->fetchColumn();
    }

    public function getPendingPaymentByTicket(int $ticketId): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT tp.* FROM ticket_payments tp
             JOIN ticket_billing tb ON tb.id = tp.billing_id
             WHERE tb.ticket_id = ? AND tp.status = 'pending'
             ORDER BY tp.created_at DESC LIMIT 1"
        );
        $stmt->execute([$ticketId]);
        return $stmt->fetch() ?: null;
    }

    public function getTopServiceRevenue(int $limit = 8): array {
        $limit = max(1, (int)$limit);
        $stmt = $this->pdo->query(
            "SELECT COALESCE(st.name, 'Tự nhập') AS name,
                    COUNT(tp.id) AS cnt,
                    COALESCE(SUM(tp.amount), 0) AS revenue
             FROM ticket_payments tp
             LEFT JOIN ticket_billing tb ON tb.id = tp.billing_id
             LEFT JOIN service_templates st ON st.id = tb.service_template_id
             WHERE tp.status = 'confirmed'
             GROUP BY st.id, st.name
             ORDER BY revenue DESC
             LIMIT {$limit}"
        );
        return $stmt->fetchAll();
    }
}
