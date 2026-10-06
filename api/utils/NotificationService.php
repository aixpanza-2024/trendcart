<?php
/**
 * Publishes an admin offer: writes it to each recipient's in-app inbox
 * (notifications table) and sends a Firebase push to their registered devices.
 */
require_once __DIR__ . '/FcmClient.php';
require_once __DIR__ . '/SubscriptionService.php';

class NotificationService {

    public static function publish($conn, array $post, $created_by) {
        $audienceWhere = "u.user_type = 'customer' AND u.is_active = 1";
        if ($post['audience'] === 'subscribers') {
            $audienceWhere .= " AND u.user_id IN (
                SELECT user_id FROM customer_subscriptions
                WHERE expires_at > NOW() AND paused_at IS NULL)";
        }

        $insert = $conn->prepare(
            "INSERT INTO admin_notifications (title, body, link_url, coupon_code, audience, created_by)
             VALUES (:title, :body, :link, :coupon, :audience, :by)"
        );
        $insert->execute([
            ':title'    => $post['title'],
            ':body'     => $post['body'],
            ':link'     => $post['link_url'],
            ':coupon'   => $post['coupon_code'],
            ':audience' => $post['audience'],
            ':by'       => $created_by,
        ]);
        $notification_id = (int)$conn->lastInsertId();

        $inboxMessage = $post['body'] . ($post['coupon_code'] ? " Use code {$post['coupon_code']}." : '');
        $fanout = $conn->prepare(
            "INSERT INTO notifications (user_id, notification_type, title, message, related_id, related_type)
             SELECT u.user_id, 'offer', :title, :message, :nid, 'admin_notification'
             FROM users u WHERE {$audienceWhere}"
        );
        $fanout->execute([':title' => $post['title'], ':message' => $inboxMessage, ':nid' => $notification_id]);
        $recipients = $fanout->rowCount();

        $tokenStmt = $conn->query(
            "SELECT dt.token FROM device_tokens dt
             JOIN users u ON u.user_id = dt.user_id
             WHERE {$audienceWhere}"
        );
        $tokens = $tokenStmt->fetchAll(PDO::FETCH_COLUMN);

        $fcm = new FcmClient();
        $sent = 0;
        $failed = 0;
        $pushStatus = null;

        if (!$fcm->isConfigured()) {
            $pushStatus = 'Push not sent: Firebase is not configured on this server';
        } elseif (empty($tokens)) {
            $pushStatus = 'No registered Android devices in this audience yet';
        } else {
            $pushData = ['notification_id' => (string)$notification_id];
            if ($post['link_url']) $pushData['link_url'] = $post['link_url'];
            if ($post['coupon_code']) $pushData['coupon_code'] = $post['coupon_code'];

            $delete = $conn->prepare("DELETE FROM device_tokens WHERE token = :token");
            $lastError = null;
            foreach ($tokens as $token) {
                try {
                    $result = $fcm->sendToToken($token, $post['title'], $post['body'], $pushData);
                } catch (Exception $e) {
                    $result = ['ok' => false, 'unregistered' => false, 'error' => $e->getMessage()];
                }
                if ($result['ok']) {
                    $sent++;
                } else {
                    $failed++;
                    $lastError = $result['error'];
                    if ($result['unregistered']) {
                        $delete->execute([':token' => $token]);
                    }
                }
            }
            $pushStatus = "Sent to {$sent} device(s)";
            if ($failed > 0) $pushStatus .= ", {$failed} failed" . ($lastError ? ": {$lastError}" : '');
        }

        $update = $conn->prepare(
            "UPDATE admin_notifications
             SET recipients_count = :rc, push_sent_count = :ps, push_failed_count = :pf, push_status = :st
             WHERE notification_id = :nid"
        );
        $update->execute([
            ':rc'  => $recipients,
            ':ps'  => $sent,
            ':pf'  => $failed,
            ':st'  => mb_substr($pushStatus, 0, 255),
            ':nid' => $notification_id,
        ]);

        return [
            'notification_id' => $notification_id,
            'recipients'      => $recipients,
            'push_sent'       => $sent,
            'push_failed'     => $failed,
            'push_status'     => $pushStatus,
        ];
    }
}
?>
