<?php
/**
 * Customer reminder email template.
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
        <?php echo esc_html(sprintf(
            /* translators: %s: experience title. */
            __('Ci vediamo presto per %s', 'fp-experiences'),
            (string) ($experience['title'] ?? '')
        )); ?>
    </h1>

    <p style="margin:0 0 16px;">
        <?php esc_html_e('Manca poco alla tua esperienza: ecco un promemoria con data, orario e punto di incontro.', 'fp-experiences'); ?>
    </p>

    <div style="padding:16px; border:1px solid #e2e8f0; border-radius:8px; background:#f7fafc; margin-bottom:20px;">
        <p style="margin:0 0 8px;">
            <strong><?php esc_html_e('Data', 'fp-experiences'); ?>:</strong>
            <?php echo esc_html((string) ($slot['start_local_date'] ?? '')); ?>
        </p>
        <p style="margin:0 0 8px;">
            <strong><?php esc_html_e('Orario', 'fp-experiences'); ?>:</strong>
            <?php echo esc_html($time_label); ?>
        </p>
        <?php if (! empty($experience['meeting_point'])) : ?>
            <p style="margin:0;">
                <strong><?php esc_html_e('Punto di incontro', 'fp-experiences'); ?>:</strong>
                <?php echo esc_html((string) $experience['meeting_point']); ?>
            </p>
        <?php endif; ?>
    </div>

    <p style="margin:0 0 20px;">
        <?php esc_html_e('Ricorda di portare con te un documento valido e di arrivare con qualche minuto di anticipo.', 'fp-experiences'); ?>
    </p>

    <p style="margin:0 0 20px;">
        <?php esc_html_e('Hai già aggiunto l’evento al calendario?', 'fp-experiences'); ?>
        <?php if ($google_link) : ?>
            <a href="<?php echo esc_url((string) $google_link); ?>" style="color:#0b7285; text-decoration:none;">
                <?php esc_html_e('Aggiungi con un clic', 'fp-experiences'); ?>
            </a>
        <?php endif; ?>
    </p>

    <p style="margin:0; color:#556987; font-size:13px;">
        <?php esc_html_e('Per qualsiasi richiesta rispondi a questa email: il nostro team è a tua disposizione.', 'fp-experiences'); ?>
    </p>
</div>
