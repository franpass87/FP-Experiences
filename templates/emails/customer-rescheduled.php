<?php
/**
 * Customer reservation rescheduled email template.
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
$reschedule = isset($email_context['reschedule']) && is_array($email_context['reschedule']) ? $email_context['reschedule'] : [];
$previous = isset($reschedule['previous']) && is_array($reschedule['previous']) ? $reschedule['previous'] : [];
$current = isset($reschedule['current']) && is_array($reschedule['current']) ? $reschedule['current'] : [];

if (! $current) {
    $current = [
        'start_local_date' => (string) ($slot['start_local_date'] ?? ''),
        'start_local_time' => (string) ($slot['start_local_time'] ?? ''),
        'end_local_time' => (string) ($slot['end_local_time'] ?? ''),
        'timezone' => (string) ($slot['timezone'] ?? ''),
    ];
}

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
<div style="font-family:'Helvetica Neue',Arial,sans-serif;color:#1f2933;line-height:1.6;">
    <h1 style="font-size:22px;margin:0 0 16px;color:#0b3d2e;">
        <?php echo esc_html($translate('customer_rescheduled.heading', [(string) ($experience['title'] ?? '')])); ?>
    </h1>

    <p style="margin:0 0 16px;">
        <?php echo esc_html($translate('customer_rescheduled.intro')); ?>
    </p>

    <?php if ($old_date || $old_time_label) : ?>
        <div style="padding:14px;border:1px solid #fecaca;border-radius:8px;background:#fff1f2;margin-bottom:12px;">
            <p style="margin:0 0 6px;font-weight:600;color:#b91c1c;">
                <?php echo esc_html($translate('customer_rescheduled.previous_slot')); ?>
            </p>
            <p style="margin:0;color:#7f1d1d;">
                <?php echo esc_html($old_date); ?>
                <?php if ($old_time_label) : ?>
                    <span> - <?php echo esc_html($old_time_label); ?></span>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <div style="padding:16px;border:1px solid #bbf7d0;border-radius:8px;background:#f0fdf4;margin-bottom:20px;">
        <p style="margin:0 0 6px;font-weight:600;color:#15803d;">
            <?php echo esc_html($translate('customer_rescheduled.new_slot')); ?>
        </p>
        <p style="margin:0;color:#166534;">
            <?php echo esc_html($new_date); ?>
            <?php if ($new_time_label) : ?>
                <span> - <?php echo esc_html($new_time_label); ?></span>
            <?php endif; ?>
            <?php if (! empty($current['timezone'])) : ?>
                <span style="color:#365314;"> (<?php echo esc_html((string) $current['timezone']); ?>)</span>
            <?php endif; ?>
        </p>
    </div>

    <p style="margin:0;color:#556987;font-size:13px;">
        <?php echo esc_html($translate('customer_rescheduled.support')); ?>
    </p>
</div>
