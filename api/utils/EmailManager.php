<?php
/**
 * Email Manager
 * Handles email sending via PHPMailer SMTP
 */

use PHPMailer\PHPMailer\PHPMailer;

// Load PHPMailer (installed via composer)
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// Load SMTP credentials (not committed to git)
require_once dirname(__DIR__) . '/config/email_config.php';

class EmailManager {

    /**
     * Create and configure a PHPMailer instance
     * @return PHPMailer
     */
    private function createMailer() {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';

        return $mail;
    }

    /**
     * Send OTP email
     */
    public function sendOTPEmail($to_email, $to_name, $otp, $purpose = 'registration') {
        if ($this->isDevelopmentMode()) {
            error_log("OTP Email for {$to_email}: {$otp}");
            return true;
        }

        try {
            $mail = $this->createMailer();
            $mail->addAddress($to_email, $to_name);
            $mail->Subject  = $this->getEmailSubject($purpose);
            $mail->Body     = $this->getEmailTemplate($to_name, $otp, $purpose);
            $mail->AltBody  = "Hello {$to_name},\n\n" . $this->getGreeting($purpose) . "\n\nYour OTP: {$otp}\n\nThis OTP expires in 10 minutes.\n\nIf you did not request this, please ignore this email.\n\nTrenCart";
            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("OTP email failed to {$to_email}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send order confirmation email to customer
     */
    public function sendOrderConfirmationEmail($to_email, $to_name, $order_data) {
        if ($this->isDevelopmentMode()) {
            error_log("Order Confirmation Email to {$to_email}: Order #{$order_data['order_number']}");
            return true;
        }

        try {
            $mail = $this->createMailer();
            $mail->addAddress($to_email, $to_name);
            $mail->Subject  = "TrenCart - Order Confirmed #{$order_data['order_number']}";
            $mail->Body     = $this->getOrderConfirmationTemplate($to_name, $order_data);
            $mail->AltBody  = "Hi {$to_name}, your order #{$order_data['order_number']} has been placed successfully.\nTotal: Rs." . number_format($order_data['total'], 2) . "\nPayment: Cash on Delivery.\n\nTrenCart";
            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("Order confirmation email failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send new order notification email to admin
     */
    public function sendNewOrderAdminEmail($admin_email, $order_data) {
        if ($this->isDevelopmentMode()) {
            error_log("Admin Order Email to {$admin_email}: Order #{$order_data['order_number']} - Rs.{$order_data['total']}");
            return true;
        }

        try {
            $mail = $this->createMailer();
            $mail->addAddress($admin_email);
            $mail->Subject  = "TrenCart - New Order #{$order_data['order_number']}";
            $mail->Body     = $this->getAdminOrderTemplate($order_data);
            $mail->AltBody  = "New order #{$order_data['order_number']} received.\nTotal: Rs." . number_format($order_data['total'], 2) . "\nPayment: Cash on Delivery.\n\nTrenCart Admin";
            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("Admin order email failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send new order notification to a shop owner (only their items)
     */
    public function sendShopOrderEmail($to_email, $shop_name, $order_data, $shop_items) {
        if ($this->isDevelopmentMode()) {
            error_log("Shop Order Email to {$to_email} ({$shop_name}): Order #{$order_data['order_number']}");
            return true;
        }

        try {
            $mail = $this->createMailer();
            $mail->addAddress($to_email, $shop_name);
            $mail->Subject  = "TrenCart - New Order #{$order_data['order_number']} for {$shop_name}";
            $mail->Body     = $this->getShopOrderTemplate($shop_name, $order_data, $shop_items);
            $mail->AltBody  = "Hi {$shop_name}, you have a new order #{$order_data['order_number']}.\nPlease log in to your shop panel to process it.\n\nTrenCart";
            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log("Shop order email failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Auto-detect development mode based on hostname.
     */
    private function isDevelopmentMode() {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || strpos($host, 'localhost:') === 0;
    }

    // -------------------------------------------------------
    // Email subject helpers
    // -------------------------------------------------------

    private function getEmailSubject($purpose) {
        switch($purpose) {
            case 'registration':    return "TrenCart - Verify Your Email";
            case 'login':           return "TrenCart - Your Login OTP";
            case 'password_reset':  return "TrenCart - Password Reset OTP";
            default:                return "TrenCart - Verification Code";
        }
    }

    private function getGreeting($purpose) {
        switch($purpose) {
            case 'registration':   return "Thank you for registering with TrenCart! Please use the following OTP to verify your email address:";
            case 'login':          return "Your login OTP for TrenCart is:";
            case 'password_reset': return "You requested to reset your password. Please use the following OTP:";
            default:               return "Your verification code is:";
        }
    }

    // -------------------------------------------------------
    // Email templates
    // -------------------------------------------------------

    private function getEmailTemplate($name, $otp, $purpose) {
        $greeting = $this->getGreeting($purpose);
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #1a1a1a; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f8f9fa; padding: 30px; margin: 20px 0; border-radius: 8px; }
                .otp-box { background-color: white; border: 2px solid #1a1a1a; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 8px; margin: 20px 0; border-radius: 8px; }
                .footer { text-align: center; color: #6c757d; font-size: 12px; padding: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'><h1>TrenCart</h1></div>
                <div class='content'>
                    <h2>Hello, {$name}!</h2>
                    <p>{$greeting}</p>
                    <div class='otp-box'>{$otp}</div>
                    <p><strong>This OTP will expire in 10 minutes.</strong></p>
                    <p>If you didn't request this code, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>&copy; 2026 TrenCart. All rights reserved.</p>
                    <p>This is an automated email. Please do not reply to this message.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    private function getOrderConfirmationTemplate($name, $d) {
        $rows = '';
        foreach ($d['items'] as $item) {
            $size_label = !empty($item['selected_size'])
                ? " <span style='font-size:11px;background:#eee;padding:1px 6px;border-radius:4px;'>{$item['selected_size']}</span>"
                : '';
            $rows .= "<tr>
                <td style='padding:8px;border-bottom:1px solid #eee;'>{$item['product_name']}{$size_label}</td>
                <td style='padding:8px;border-bottom:1px solid #eee;text-align:center;'>{$item['quantity']}</td>
                <td style='padding:8px;border-bottom:1px solid #eee;text-align:right;'>&#8377;" . number_format($item['price'], 2) . "</td>
                <td style='padding:8px;border-bottom:1px solid #eee;text-align:right;'>&#8377;" . number_format($item['subtotal'], 2) . "</td>
            </tr>";
        }
        $s = $d['shipping_info'];
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;margin:0;padding:0;'>
        <div style='max-width:600px;margin:0 auto;'>
            <div style='background:#1a1a1a;color:#fff;padding:24px;text-align:center;'>
                <h1 style='margin:0;font-size:28px;letter-spacing:2px;'>TrenCart</h1>
            </div>
            <div style='padding:30px;background:#f8f9fa;'>
                <h2 style='color:#28a745;'>&#10003; Order Placed Successfully!</h2>
                <p>Hi <strong>{$name}</strong>, thank you for your order.</p>
                <p style='font-size:18px;font-weight:bold;'>Order # {$d['order_number']}</p>
                <table style='width:100%;border-collapse:collapse;margin:20px 0;background:#fff;border-radius:8px;overflow:hidden;'>
                    <thead><tr style='background:#1a1a1a;color:#fff;'>
                        <th style='padding:10px;text-align:left;'>Product</th>
                        <th style='padding:10px;text-align:center;'>Qty</th>
                        <th style='padding:10px;text-align:right;'>Price</th>
                        <th style='padding:10px;text-align:right;'>Total</th>
                    </tr></thead>
                    <tbody>{$rows}</tbody>
                </table>
                <table style='width:100%;margin:0 0 20px;'>
                    <tr><td>Subtotal</td><td style='text-align:right;'>&#8377;" . number_format($d['subtotal'], 2) . "</td></tr>
                    <tr><td>GST (18%)</td><td style='text-align:right;'>&#8377;" . number_format($d['tax'], 2) . "</td></tr>
                    <tr><td>Shipping</td><td style='text-align:right;'>" . ($d['shipping'] == 0 ? 'FREE' : '&#8377;' . number_format($d['shipping'], 2)) . "</td></tr>
                    <tr style='font-size:18px;font-weight:bold;border-top:2px solid #333;'><td>Total</td><td style='text-align:right;'>&#8377;" . number_format($d['total'], 2) . "</td></tr>
                </table>
                <div style='background:#fff;padding:16px;border-radius:8px;margin-bottom:20px;'>
                    <strong>Delivery Address:</strong><br>
                    {$s['full_name']}, {$s['phone']}<br>
                    {$s['address']}, {$s['city']}, {$s['state']} - {$s['pincode']}
                </div>
                <p style='background:#fff3cd;padding:12px;border-radius:6px;'><strong>Payment:</strong> Cash on Delivery &mdash; Pay when you receive your order.</p>
            </div>
            <div style='text-align:center;color:#999;font-size:12px;padding:20px;'>
                &copy; 2026 TrenCart. All rights reserved.
            </div>
        </div></body></html>";
    }

    private function getAdminOrderTemplate($d) {
        $rows = '';
        foreach ($d['items'] as $item) {
            $size_label = !empty($item['selected_size'])
                ? " <span style='font-size:11px;background:#eee;padding:1px 6px;border-radius:4px;'>{$item['selected_size']}</span>"
                : '';
            $rows .= "<tr>
                <td style='padding:8px;border-bottom:1px solid #eee;'>{$item['product_name']}{$size_label}</td>
                <td style='padding:8px;border-bottom:1px solid #eee;text-align:center;'>{$item['quantity']}</td>
                <td style='padding:8px;border-bottom:1px solid #eee;text-align:right;'>&#8377;" . number_format($item['subtotal'], 2) . "</td>
            </tr>";
        }
        $s = $d['shipping_info'];
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;margin:0;padding:0;'>
        <div style='max-width:600px;margin:0 auto;'>
            <div style='background:#1a1a1a;color:#fff;padding:24px;text-align:center;'>
                <h1 style='margin:0;'>TrenCart Admin</h1>
            </div>
            <div style='padding:30px;background:#f8f9fa;'>
                <h2>&#128222; New Order Received</h2>
                <p style='font-size:18px;font-weight:bold;'>Order # {$d['order_number']}</p>
                <p><strong>Customer:</strong> {$s['full_name']} ({$s['phone']})</p>
                <p><strong>Delivery:</strong> {$s['address']}, {$s['city']}, {$s['state']} - {$s['pincode']}</p>
                <table style='width:100%;border-collapse:collapse;margin:20px 0;background:#fff;'>
                    <thead><tr style='background:#1a1a1a;color:#fff;'>
                        <th style='padding:10px;text-align:left;'>Product</th>
                        <th style='padding:10px;text-align:center;'>Qty</th>
                        <th style='padding:10px;text-align:right;'>Subtotal</th>
                    </tr></thead>
                    <tbody>{$rows}</tbody>
                </table>
                <p style='font-size:20px;font-weight:bold;'>Order Total: &#8377;" . number_format($d['total'], 2) . "</p>
                <p><strong>Payment:</strong> Cash on Delivery</p>
            </div>
            <div style='text-align:center;color:#999;font-size:12px;padding:20px;'>
                TrenCart Admin Notification
            </div>
        </div></body></html>";
    }

    private function getShopOrderTemplate($shop_name, $d, $shop_items) {
        $rows = '';
        $shop_total = 0;
        foreach ($shop_items as $item) {
            $size_label = !empty($item['selected_size']) ? " <span style='font-size:11px;background:#eee;padding:1px 5px;border-radius:4px;'>{$item['selected_size']}</span>" : '';
            $rows .= "<tr>
                <td style='padding:8px;border-bottom:1px solid #eee;'>{$item['product_name']}{$size_label}</td>
                <td style='padding:8px;border-bottom:1px solid #eee;text-align:center;'>{$item['quantity']}</td>
                <td style='padding:8px;border-bottom:1px solid #eee;text-align:right;'>&#8377;" . number_format($item['price'], 2) . "</td>
                <td style='padding:8px;border-bottom:1px solid #eee;text-align:right;'>&#8377;" . number_format($item['subtotal'], 2) . "</td>
            </tr>";
            $shop_total += $item['subtotal'];
        }
        $s = $d['shipping_info'];
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;margin:0;padding:0;'>
        <div style='max-width:600px;margin:0 auto;'>
            <div style='background:#1a1a1a;color:#fff;padding:24px;text-align:center;'>
                <h1 style='margin:0;font-size:24px;'>TrenCart &mdash; Shop Notification</h1>
            </div>
            <div style='padding:30px;background:#f8f9fa;'>
                <h2 style='color:#1a1a1a;'>&#128230; New Order for {$shop_name}</h2>
                <p style='font-size:18px;font-weight:bold;'>Order # {$d['order_number']}</p>
                <p><strong>Customer:</strong> {$s['full_name']} &mdash; {$s['phone']}</p>
                <p><strong>Deliver to:</strong> {$s['address']}, {$s['city']}, {$s['state']} &mdash; {$s['pincode']}</p>
                <table style='width:100%;border-collapse:collapse;margin:20px 0;background:#fff;border-radius:8px;overflow:hidden;'>
                    <thead><tr style='background:#1a1a1a;color:#fff;'>
                        <th style='padding:10px;text-align:left;'>Product</th>
                        <th style='padding:10px;text-align:center;'>Qty</th>
                        <th style='padding:10px;text-align:right;'>Price</th>
                        <th style='padding:10px;text-align:right;'>Total</th>
                    </tr></thead>
                    <tbody>{$rows}</tbody>
                </table>
                <p style='font-size:18px;font-weight:bold;text-align:right;'>Your Items Total: &#8377;" . number_format($shop_total, 2) . "</p>
                <p style='background:#fff3cd;padding:12px;border-radius:6px;'><strong>Payment:</strong> Cash on Delivery &mdash; Collect payment on delivery.</p>
                <p style='color:#555;font-size:13px;'>Please log in to your <a href='https://trencart.com/shop/orders.html' style='color:#1a1a1a;'>shop panel</a> to confirm and process this order.</p>
            </div>
            <div style='text-align:center;color:#999;font-size:12px;padding:20px;'>
                TrenCart &mdash; Shop Owner Notification
            </div>
        </div></body></html>";
    }
}
?>
