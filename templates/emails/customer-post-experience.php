<?php
/**
 * Post experience follow-up email template.
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

$fp_email_settings = get_option('fp_exp_emails', []);
$fp_email_settings = is_array($fp_email_settings) ? $fp_email_settings : [];
$review_url = ! empty($fp_email_settings['review_url']) ? $fp_email_settings['review_url'] : '';
$review_link = $review_url ?: ($experience['permalink'] ?? '');
?>
<div style="font-family: 'Helvetica Neue', Arial, sans-serif; color:#1f2933; line-height:1.6;">
    <h1 style="font-size:22px; margin:0 0 16px; color:#0b3d2e;">
        <?php echo esc_html($translate('customer_post_experience.heading', [(string) ($experience['title'] ?? '')])); ?>
    </h1>

    <p style="margin:0 0 16px;">
        <?php echo esc_html($translate('customer_post_experience.thanks')); ?>
    </p>

    <p style="margin:0 0 20px;">
        <?php echo esc_html($translate('customer_post_experience.review_request')); ?>
    </p>

    <?php if (! empty($review_link)) : ?>
        <p style="margin:0 0 20px;">
            <a href="<?php echo esc_url((string) $review_link); ?>" style="display:inline-block; background:#0b7285; color:#ffffff; padding:10px 24px; border-radius:6px; text-decoration:none; font-weight:600;">
                <?php echo esc_html($translate('customer_post_experience.leave_review')); ?>
            </a>
        </p>
    <?php endif; ?>

    <p style="margin:0; color:#556987; font-size:13px;">
        <?php echo esc_html($translate('customer_post_experience.signoff')); ?>
    </p>
</div>
