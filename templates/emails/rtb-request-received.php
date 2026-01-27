<?php
/**
 * RTB Request Received email template.
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
$tickets = $email_context['tickets'] ?? [];
$customer = $email_context['customer'] ?? [];

$meeting_point = $experience['meeting_point'] ?? '';
$start_time = (string) ($slot['start_local_time'] ?? '');
$end_time = (string) ($slot['end_local_time'] ?? '');
$time_label = $start_time;

if ($start_time && $end_time) {
    $time_label .= ' – ' . $end_time;
} elseif ($end_time && ! $start_time) {
    $time_label = $end_time;
}
?>
<div style="font-family: 'Helvetica Neue', Arial, sans-serif; color:#1f2933; line-height:1.6;">
    <h1 style="font-size:22px; margin:0 0 16px; color:#0b3d2e;">
        <?php echo esc_html($translate('rtb_request.heading', [(string) ($experience['title'] ?? '')])); ?>
    </h1>

    <p style="margin:0 0 16px;">
        <?php echo esc_html($translate('rtb_request.intro')); ?>
    </p>

    <div style="padding:16px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:20px; background:#f7fafc;">
        <p style="margin:0 0 8px;">
            <strong><?php echo esc_html($translate('common.date')); ?>:</strong>
            <?php echo esc_html((string) ($slot['start_local_date'] ?? '')); ?>
        </p>
        <p style="margin:0 0 8px;">
            <strong><?php echo esc_html($translate('common.time')); ?>:</strong>
            <?php echo esc_html($time_label); ?>
            <?php if (! empty($slot['timezone'])) : ?>
                <span style="color:#556987;">(<?php echo esc_html((string) $slot['timezone']); ?>)</span>
            <?php endif; ?>
        </p>
        <?php if ($meeting_point) : ?>
            <p style="margin:0 0 8px;">
                <strong><?php echo esc_html($translate('common.meeting_point')); ?>:</strong>
                <?php echo esc_html((string) $meeting_point); ?>
            </p>
        <?php endif; ?>
        <?php if (! empty($experience['short_description'])) : ?>
            <p style="margin:0; color:#364152;">
                <?php echo esc_html((string) $experience['short_description']); ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if ($tickets) : ?>
    <h2 style="font-size:18px; margin:0 0 12px; color:#0b3d2e;">
        <?php echo esc_html($translate('customer_confirmation.participant_heading')); ?>
    </h2>
    <table role="presentation" style="width:100%; border-collapse:collapse; margin-bottom:20px;">
        <tbody>
            <?php foreach ($tickets as $ticket) : ?>
                <tr>
                    <td style="padding:6px 0; border-bottom:1px solid #e2e8f0;">
                        <?php echo esc_html((string) ($ticket['label'] ?? $ticket['type'] ?? '')); ?>
                    </td>
                    <td style="padding:6px 0; border-bottom:1px solid #e2e8f0; text-align:right;">
                        × <?php echo esc_html((string) ($ticket['quantity'] ?? '0')); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div style="padding:16px; border:2px solid #3b82f6; border-radius:8px; margin-bottom:24px; background:#eff6ff;">
        <p style="margin:0; font-size:16px; color:#1e40af;">
            <?php echo esc_html($translate('rtb_request.status_pending')); ?>
        </p>
    </div>

    <hr style="border:0; border-top:1px solid #e2e8f0; margin:24px 0;">

    <p style="margin:0; font-size:14px; color:#556987;">
        <?php echo esc_html($translate('rtb_request.footer_note')); ?>
    </p>
</div>
