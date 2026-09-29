<?php
/**
 * Razorpay API client — plain cURL against their REST API (no composer/SDK
 * available on this server; the API itself is simple HTTP Basic Auth + JSON,
 * same pattern already used for the SMS gateway in SMSManager.php).
 * Docs: https://razorpay.com/docs/api/orders/ and /payments/
 */

class RazorpayClient {
    const API_BASE = 'https://api.razorpay.com/v1';

    private $keyId;
    private $keySecret;

    public function __construct() {
        $file = __DIR__ . '/../config/razorpay_config.php';
        if (!file_exists($file)) {
            throw new Exception('Online payment is not configured on this server.');
        }
        require_once $file;
        if (!defined('RAZORPAY_KEY_ID') || !defined('RAZORPAY_KEY_SECRET')) {
            throw new Exception('Online payment is not configured on this server.');
        }
        $this->keyId     = RAZORPAY_KEY_ID;
        $this->keySecret = RAZORPAY_KEY_SECRET;
    }

    public function getKeyId() {
        return $this->keyId;
    }

    /**
     * Create a Razorpay Order for the given rupee amount.
     * @param float $amountRupees
     * @param string $receipt Our own reference (e.g. a draft/cart reference), max 40 chars
     * @param array $notes Arbitrary key/value pairs Razorpay stores alongside the order
     * @return array Decoded Razorpay order object (has 'id', 'amount', 'currency', ...)
     * @throws Exception on any API/network failure
     */
    public function createOrder($amountRupees, $receipt, $notes = []) {
        $amountPaise = (int)round($amountRupees * 100);
        if ($amountPaise < 100) { // Razorpay's own minimum is ₹1
            throw new Exception('Order amount is too small for online payment.');
        }

        $body = [
            'amount'   => $amountPaise,
            'currency' => 'INR',
            'receipt'  => substr($receipt, 0, 40),
        ];
        if (!empty($notes)) {
            $body['notes'] = $notes;
        }

        return $this->request('POST', '/orders', $body);
    }

    /**
     * Fetch a payment by id — the authoritative source of truth for its actual
     * status and amount, used to double-check a client-submitted signature
     * before an order is ever created.
     * @return array Decoded Razorpay payment object
     * @throws Exception on any API/network failure
     */
    public function fetchPayment($paymentId) {
        return $this->request('GET', '/payments/' . rawurlencode($paymentId));
    }

    /**
     * Verify the checkout handler's signature. This proves the payment fields
     * genuinely came from Razorpay (HMAC-SHA256 of "order_id|payment_id" with
     * the key secret) — it does NOT by itself confirm the amount or status,
     * which fetchPayment() must still be used to check.
     */
    public function verifySignature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature) {
        $expected = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $this->keySecret);
        return is_string($razorpaySignature) && hash_equals($expected, $razorpaySignature);
    }

    private function request($method, $path, $body = null) {
        $ch = curl_init(self::API_BASE . $path);
        $options = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_USERPWD        => $this->keyId . ':' . $this->keySecret,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        curl_setopt_array($ch, $options);

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            error_log("Razorpay request failed ({$method} {$path}): {$err}");
            throw new Exception('Could not reach the payment gateway. Please try again.');
        }

        $decoded = json_decode($raw, true);

        if ($status < 200 || $status >= 300) {
            $msg = $decoded['error']['description'] ?? "HTTP {$status}";
            error_log("Razorpay API error ({$method} {$path}): {$raw}");
            throw new Exception('Payment gateway error: ' . $msg);
        }

        return $decoded;
    }
}
?>
