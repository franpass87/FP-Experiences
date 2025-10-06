<?php
/**
 * Staff notification email template.
 *
 * @var array<string, mixed> $email_context
 * @var string|null $email_language
 */

use FP_Exp\Booking\EmailTranslator;

if (! defined('ABSPATH')) {
    exit;
}

if (! isset($email_context) || ! is_array($email_context)) {
    return;
}

$language = EmailTranslator::normalize($email_language ?? ($email_context['language'] ?? ''));
$translate = static function (string $key, array $args = []) use ($language): string {
    return EmailTranslator::text($key, $language, $args);
};

$experience = $email_context['experience'] ?? [];
$slot = $email_context['slot'] ?? [];
$order = $email_context['order'] ?? [];
$tickets = $email_context['tickets'] ?? [];
$addons = $email_context['addons'] ?? [];
$customer = $email_context['customer'] ?? [];
$status_label = $email_context['status_label'] ?? '';
$is_cancelled = ! empty($email_context['is_cancelled']);
$start_time = (string) ($slot['start_local_time'] ?? '');
$end_time = (string) ($slot['end_local_time'] ?? '');
$time_label = $start_time;

if ($start_time && $end_time) {
    $time_label .= ' – ' . $end_time;
} elseif ($end_time && ! $start_time) {
    $time_label = $end_time;
}

$subject_key = $is_cancelled ? 'staff_notification.subject_cancelled' : 'staff_notification.subject_new';
?>
<div style="font-family: 'Helvetica Neue', Arial, sans-serif; color:#1f2933; line-height:1.5;">
    <h1 style="font-size:20px; margin:0 0 12px; color:#0b3d2e;">
        <?php echo esc_html($translate($subject_key, [(string) ($experience['title'] ?? '')])); ?>
    </h1>

    <p style="margin:0 0 12px;">
        <?php echo esc_html($translate('staff_notification.summary')); ?>
    </p>

    <table role="presentation" style="width:100%; border-collapse:collapse; margin-bottom:16px;">
        <tbody>
            <tr>
                <td style="padding:6px 0; color:#556987; width:40%;"><?php echo esc_html($translate('common.date')); ?></td>
                <td style="padding:6px 0; text-align:right;">
                    <?php echo esc_html((string) ($slot['start_local_date'] ?? '')); ?>
                </td>
            </tr>
            <tr>
                <td style="padding:6px 0; color:#556987;"><?php echo esc_html($translate('common.time')); ?></td>
                <td style="padding:6px 0; text-align:right;">
                    <?php echo esc_html($time_label); ?>
                </td>
            </tr>
            <?php if (! empty($experience['meeting_point'])) : ?>
                <tr>
                    <td style="padding:6px 0; color:#556987;"><?php echo esc_html($translate('common.meeting_point')); ?></td>
                    <td style="padding:6px 0; text-align:right;">
                        <?php echo esc_html((string) $experience['meeting_point']); ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php if ($status_label) : ?>
                <tr>
                    <td style="padding:6px 0; color:#556987;"><?php echo esc_html($translate('common.status')); ?></td>
                    <td style="padding:6px 0; text-align:right; color:#b83227;">
                        <?php echo esc_html((string) $status_label); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h2 style="font-size:16px; margin:20px 0 8px; color:#0b3d2e;">
        <?php echo esc_html($translate('staff_notification.participants')); ?>
    </h2>
    <ul style="margin:0 0 16px 20px; padding:0;">
        <?php foreach ($tickets as $ticket) : ?>
            <li>
                <?php echo esc_html((string) ($ticket['label'] ?? $ticket['type'] ?? '')); ?> –
                <strong><?php echo esc_html((string) ($ticket['quantity'] ?? '0')); ?></strong>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($addons) : ?>
        <h2 style="font-size:16px; margin:20px 0 8px; color:#0b3d2e;">
            <?php echo esc_html($translate('staff_notification.extras')); ?>
        </h2>
        <ul style="margin:0 0 16px 20px; padding:0;">
            <?php foreach ($addons as $addon) : ?>
                <li>
                    <?php echo esc_html((string) ($addon['label'] ?? $addon['key'] ?? '')); ?> –
                    <strong><?php echo esc_html((string) ($addon['quantity'] ?? '1')); ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2 style="font-size:16px; margin:20px 0 8px; color:#0b3d2e;">
        <?php echo esc_html($translate('staff_notification.customer_contact')); ?>
    </h2>
    <p style="margin:0 0 6px;">
        <?php echo esc_html(trim((string) ($customer['name'] ?? ''))); ?>
    </p>
    <?php if (! empty($customer['email'])) : ?>
        <p style="margin:0 0 6px;">
            <a href="mailto:<?php echo esc_attr((string) $customer['email']); ?>" style="color:#0b7285; text-decoration:none;">
                <?php echo esc_html((string) $customer['email']); ?>
            </a>
        </p>
    <?php endif; ?>
    <?php if (! empty($customer['phone'])) : ?>
        <p style="margin:0 0 6px; color:#364152;">
            <?php echo esc_html((string) $customer['phone']); ?>
        </p>
    <?php endif; ?>

    <h2 style="font-size:16px; margin:20px 0 8px; color:#0b3d2e;">
        <?php echo esc_html($translate('staff_notification.order')); ?>
    </h2>
    <p style="margin:0 0 6px; color:#364152;">
        <strong><?php echo esc_html($translate('staff_notification.order_number')); ?>:</strong>
        <?php echo esc_html((string) ($order['number'] ?? $order['id'] ?? '')); ?>
    </p>
    <p style="margin:0 0 6px; color:#364152;">
        <strong><?php echo esc_html($translate('common.total')); ?>:</strong>
        <?php echo wp_kses_post((string) ($order['total'] ?? '')); ?>
    </p>
    <?php if (! empty($order['admin_url'])) : ?>
        <p style="margin:0 0 16px;">
            <a href="<?php echo esc_url((string) $order['admin_url']); ?>" style="color:#0b7285; text-decoration:none;">
                <?php echo esc_html($translate('staff_notification.open_order')); ?>
            </a>
        </p>
    <?php endif; ?>

    <?php if (! empty($order['notes'])) : ?>
        <div style="margin-top:16px; padding:12px; background:#fef3c7; border-radius:6px; color:#78350f;">
            <strong><?php echo esc_html($translate('staff_notification.customer_notes')); ?>:</strong>
            <p style="margin:8px 0 0;">
                <?php echo esc_html((string) $order['notes']); ?>
            </p>
        </div>
    <?php endif; ?>
</div>
