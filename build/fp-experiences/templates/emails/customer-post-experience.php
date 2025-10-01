<?php
/**
 * Post experience follow-up email template.
 *
 * @var array<string, mixed> $email_context
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! isset($email_context) || ! is_array($email_context)) {
    return;
}

$experience = $email_context['experience'] ?? [];
?>
<div style="font-family: 'Helvetica Neue', Arial, sans-serif; color:#1f2933; line-height:1.6;">
    <h1 style="font-size:22px; margin:0 0 16px; color:#0b3d2e;">
        <?php echo esc_html(sprintf(
            /* translators: %s: experience title. */
            __('Com’è andata %s?', 'fp-experiences'),
            (string) ($experience['title'] ?? '')
        )); ?>
    </h1>

    <p style="margin:0 0 16px;">
        <?php esc_html_e('Grazie per aver partecipato! Ci farebbe piacere ricevere un tuo feedback per continuare a migliorare.', 'fp-experiences'); ?>
    </p>

    <p style="margin:0 0 20px;">
        <?php esc_html_e('Raccontaci cosa ti è piaciuto o cosa possiamo migliorare rispondendo a questa email oppure lasciando una recensione.', 'fp-experiences'); ?>
    </p>

    <?php if (! empty($experience['permalink'])) : ?>
        <p style="margin:0 0 20px;">
            <a href="<?php echo esc_url((string) $experience['permalink']); ?>" style="color:#0b7285; text-decoration:none;">
                <?php esc_html_e('Lascia una recensione', 'fp-experiences'); ?>
            </a>
        </p>
    <?php endif; ?>

    <p style="margin:0; color:#556987; font-size:13px;">
        <?php esc_html_e('Ti aspettiamo presto per una nuova esperienza!', 'fp-experiences'); ?>
    </p>
</div>
