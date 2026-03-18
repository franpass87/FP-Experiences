<?php
/**
 * Staff reservation rescheduled email template.
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
$customer = $email_context['customer'] ?? [];
$order = $email_context['order'] ?? [];
$reschedule = isset($email_context['reschedule']) && is_array($email_context['reschedule']) ? $email_context['reschedule'] : [];
$previous = isset($reschedule['previous']) && is_array($reschedule['previous']) ? $reschedule['previous'] : [];
$current = isset($reschedule['current']) && is_array($reschedule['current']) ? $reschedule['current'] : [];

$old_date = (string) ($previous['start_local_date'] ?? '');
$old_time_start = (string) ($previous['start_local_time'] ?? '');
$old_time_end = (string) ($previous['end_local_time'] ?? '');
$old_time_label = $old_time_start;
if ($old_time_start && $old_time_end) {
    $old_time_label .= ' - ' . $old_time_end;
}

$new_date = (string) ($current['start_local_date'] ?? '');
$new_time_start = (string) ($current['start_local_time'] ?? '');
$new_time_end = (string) ($current['end_local_time'] ?? '');
$new_time_label = $new_time_start;
if ($new_time_start && $new_time_end) {
    $new_time_label .= ' - ' . $new_time_end;
}
?>
<div style="font-family:'Helvetica Neue',Arial,sans-serif;color:#1f2933;line-height:1.5;">
    <h1 style="font-size:20px;margin:0 0 12px;color:#0b3d2e;">
        <?php echo esc_html($translate('staff_rescheduled.heading', [(string) ($experience['title'] ?? '')])); ?>
    </h1>

    <p style="margin:0 0 14px;">
        <?php echo esc_html($translate('staff_rescheduled.intro')); ?>
    </p>

    <table role="presentation" style="width:100%;border-collapse:collapse;margin-bottom:14px;">
        <tbody>
            <tr>
                <td style="padding:6px 0;color:#556987;width:38%;"><?php echo esc_html($translate('staff_rescheduled.previous_slot')); ?></td>
                <td style="padding:6px 0;text-align:right;">
                    <?php echo esc_html(trim($old_date . ' ' . $old_time_label)); ?>
                </td>
            </tr>
            <tr>
                <td style="padding:6px 0;color:#556987;"><?php echo esc_html($translate('staff_rescheduled.new_slot')); ?></td>
                <td style="padding:6px 0;text-align:right;color:#166534;">
                    <?php echo esc_html(trim($new_date . ' ' . $new_time_label)); ?>
                </td>
            </tr>
            <?php if (! empty($current['timezone'])) : ?>
                <tr>
                    <td style="padding:6px 0;color:#556987;"><?php echo esc_html($translate('staff_rescheduled.timezone')); ?></td>
                    <td style="padding:6px 0;text-align:right;"><?php echo esc_html((string) $current['timezone']); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <p style="margin:0 0 6px;">
        <strong><?php echo esc_html($translate('staff_notification.customer_contact')); ?>:</strong>
        <?php echo esc_html(trim((string) ($customer['name'] ?? ''))); ?>
    </p>
    <?php if (! empty($customer['email'])) : ?>
        <p style="margin:0 0 6px;">
            <a href="mailto:<?php echo esc_attr((string) $customer['email']); ?>" style="color:#0b7285;text-decoration:none;">
                <?php echo esc_html((string) $customer['email']); ?>
            </a>
        </p>
    <?php endif; ?>

    <?php if (! empty($order['number'])) : ?>
        <p style="margin:12px 0 0;color:#475569;">
            <strong><?php echo esc_html($translate('staff_notification.order_number')); ?>:</strong>
            <?php echo esc_html((string) $order['number']); ?>
        </p>
    <?php endif; ?>
</div>
