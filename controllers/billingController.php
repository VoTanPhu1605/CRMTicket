<?php
require_once __DIR__ . '/../models/Billing.php';

class BillingController {
    private $billing;

    public function __construct() {
        $this->billing = new Billing();
    }

    // ── Service Templates ──────────────────────────────────────────────

    public function getTemplatesByCategory(int $categoryId): array {
        return $this->billing->getTemplatesByCategory($categoryId);
    }

    public function getAllTemplates(): array {
        return $this->billing->getAllTemplates();
    }

    public function saveTemplate(array $data, ?int $id = null): array {
        if (empty(trim($data['name'] ?? '')))  return ['success' => false, 'message' => 'Tên dịch vụ không được trống.'];
        if (!isset($data['price']) || (int)$data['price'] < 0) return ['success' => false, 'message' => 'Giá không hợp lệ.'];

        if ($id) {
            $ok = $this->billing->updateTemplate($id, $data);
        } else {
            $ok = $this->billing->createTemplate($data);
        }
        return $ok
            ? ['success' => true,  'message' => $id ? 'Đã cập nhật dịch vụ.' : 'Đã thêm dịch vụ.']
            : ['success' => false, 'message' => 'Lỗi lưu dịch vụ.'];
    }

    public function deleteTemplate(int $id): array {
        return $this->billing->deleteTemplate($id)
            ? ['success' => true,  'message' => 'Đã xóa dịch vụ.']
            : ['success' => false, 'message' => 'Không thể xóa.'];
    }

    // ── Billing ────────────────────────────────────────────────────────

    public function getBillingByTicket(int $ticketId): ?array {
        return $this->billing->getBillingByTicket($ticketId);
    }

    public function setBilling(int $ticketId, array $data): array {
        $price = (int)($data['price'] ?? 0);
        if ($price < 0) return ['success' => false, 'message' => 'Giá không hợp lệ.'];

        $cu = getCurrentUser();
        $ok = $this->billing->createBilling([
            'ticket_id'           => $ticketId,
            'service_template_id' => $data['service_template_id'] ?: null,
            'price'               => $price,
            'note'                => $data['note'] ?? '',
            'created_by'          => $cu['id'],
        ]);
        return $ok
            ? ['success' => true,  'message' => 'Đã thiết lập giá dịch vụ.']
            : ['success' => false, 'message' => 'Lỗi lưu thông tin giá.'];
    }

    public function waiveBilling(int $billingId): array {
        $ok = $this->billing->updateBillingStatus($billingId, 'waived', null);
        return $ok
            ? ['success' => true,  'message' => 'Đã miễn phí dịch vụ.']
            : ['success' => false, 'message' => 'Lỗi.'];
    }

    // ── Payment ────────────────────────────────────────────────────────

    public function initiatePayment(int $ticketId, string $method): array {
        $billing = $this->billing->getBillingByTicket($ticketId);
        if (!$billing) return ['success' => false, 'message' => 'Chưa thiết lập giá cho ticket này.'];
        if ($billing['payment_status'] === 'paid') return ['success' => false, 'message' => 'Ticket đã được thanh toán.'];
        if ($billing['payment_status'] === 'waived') return ['success' => false, 'message' => 'Ticket này được miễn phí.'];
        if ((int)$billing['price'] <= 0) return ['success' => false, 'message' => 'Giá dịch vụ là 0đ, không cần thanh toán.'];

        // Cancel any existing pending payment for this ticket
        $pending = $this->billing->getPendingPaymentByTicket($ticketId);
        if ($pending) $this->billing->cancelPayment($pending['id']);

        $qrMethods = ['momo', 'zalopay', 'vnpay', 'bank_transfer'];
        $momoRef = in_array($method, $qrMethods) ? strtoupper($method) . '-' . $ticketId . '-' . time() : null;
        $cu = getCurrentUser();

        $paymentId = $this->billing->createPayment([
            'ticket_id'  => $ticketId,
            'billing_id' => $billing['id'],
            'method'     => $method,
            'amount'     => $billing['price'],
            'momo_ref'   => $momoRef,
            'created_by' => $cu['id'],
        ]);

        // Update billing to pending state
        $this->billing->updateBillingStatus($billing['id'], 'pending', $method);

        return [
            'success'    => true,
            'payment_id' => $paymentId,
            'amount'     => (int)$billing['price'],
            'momo_ref'   => $momoRef,
        ];
    }

    public function confirmPayment(int $paymentId): array {
        $payment = $this->billing->getPayment($paymentId);
        if (!$payment) return ['success' => false, 'message' => 'Không tìm thấy giao dịch.'];
        if ($payment['status'] !== 'pending') return ['success' => false, 'message' => 'Giao dịch không ở trạng thái chờ.'];

        $cu = getCurrentUser();
        $ok = $this->billing->confirmPayment($paymentId, $cu['id']);
        if (!$ok) return ['success' => false, 'message' => 'Không thể xác nhận. Vui lòng thử lại.'];

        // Mark billing as paid
        $this->billing->updateBillingStatus($payment['billing_id'], 'paid', $payment['method']);

        return ['success' => true, 'message' => 'Đã xác nhận thanh toán thành công.'];
    }

    public function cancelPayment(int $paymentId): array {
        $payment = $this->billing->getPayment($paymentId);
        if (!$payment) return ['success' => false, 'message' => 'Không tìm thấy giao dịch.'];

        $this->billing->cancelPayment($paymentId);
        // Reset billing back to unpaid
        $this->billing->updateBillingStatus($payment['billing_id'], 'unpaid', null);

        return ['success' => true, 'message' => 'Đã hủy giao dịch.'];
    }

    public function getPaymentsByTicket(int $ticketId): array {
        return $this->billing->getPaymentsByTicket($ticketId);
    }

    // ── Revenue Stats ──────────────────────────────────────────────────

    public function getRevenueStats(): array {
        return [
            'today'      => $this->billing->getRevenueToday(),
            'this_month' => $this->billing->getRevenueThisMonth(),
            'this_year'  => $this->billing->getRevenueThisYear(),
        ];
    }

    public function getRevenueByMonth(string $year): array {
        return $this->billing->getRevenueByMonth($year);
    }

    public function getRevenueByYear(int $count = 3): array {
        return $this->billing->getRevenueByYear($count);
    }

    public function getRevenueComparison(string $year1, string $year2): array {
        return $this->billing->getRevenueComparison($year1, $year2);
    }

    public function getUnpaidCount(): int {
        return $this->billing->getUnpaidCount();
    }

    public function getAllBilling(): array {
        return $this->billing->getAllBilling();
    }

    public function getTopServiceRevenue(int $limit = 8): array {
        return $this->billing->getTopServiceRevenue($limit);
    }
}
