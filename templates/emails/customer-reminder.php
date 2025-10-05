<?php
/**
 * Customer reminder email template.
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
$ics = $email_context['ics'] ?? [];
$google_link = $ics['google_link'] ?? '';
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
        <?php echo esc_html($translate('customer_reminder.heading', [(string) ($experience['title'] ?? '')])); ?>
    </h1>

    <p style="margin:0 0 16px;">
        <?php echo esc_html($translate('customer_reminder.intro')); ?>
    </p>

    <div style="padding:16px; border:1px solid #e2e8f0; border-radius:8px; background:#f7fafc; margin-bottom:20px;">
        <p style="margin:0 0 8px;">
            <strong><?php echo esc_html($translate('common.date')); ?>:</strong>
            <?php echo esc_html((string) ($slot['start_local_date'] ?? '')); ?>
        </p>
        <p style="margin:0 0 8px;">
            <strong><?php echo esc_html($translate('common.time')); ?>:</strong>
            <?php echo esc_html($time_label); ?>
        </p>
        <?php if (! empty($experience['meeting_point'])) : ?>
            <p style="margin:0;">
                <strong><?php echo esc_html($translate('common.meeting_point')); ?>:</strong>
                <?php echo esc_html((string) $experience['meeting_point']); ?>
            </p>
        <?php endif; ?>
    </div>

    <p style="margin:0 0 20px;">
        <?php echo esc_html($translate('customer_reminder.remember')); ?>
    </p>

    <p style="margin:0 0 20px;">
        <?php echo esc_html($translate('customer_reminder.calendar_question')); ?>
        <?php if ($google_link) : ?>
            <a href="<?php echo esc_url((string) $google_link); ?>" style="color:#0b7285; text-decoration:none;">
                <?php echo esc_html($translate('customer_reminder.calendar_cta')); ?>
            </a>
        <?php endif; ?>
    </p>

    <p style="margin:0; color:#556987; font-size:13px;">
        <?php echo esc_html($translate('customer_reminder.support')); ?>
    </p>
</div>
