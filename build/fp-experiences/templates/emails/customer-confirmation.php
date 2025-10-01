<?php
/**
 * Customer confirmation email template.
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
$order = $email_context['order'] ?? [];
$tickets = $email_context['tickets'] ?? [];
$addons = $email_context['addons'] ?? [];
$customer = $email_context['customer'] ?? [];
$ics = $email_context['ics'] ?? [];

$meeting_point = $experience['meeting_point'] ?? '';
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
            __("Grazie per aver prenotato %s", 'fp-experiences'),
            (string) ($experience['title'] ?? '')
        )); ?>
    </h1>

    <p style="margin:0 0 12px;">
        <?php echo esc_html__(
            'Di seguito trovi tutti i dettagli della tua esperienza. Presentati con qualche minuto di anticipo e porta con te questa email (o il QR code se disponibile).',
            'fp-experiences'
        ); ?>
    </p>

    <div style="padding:16px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:20px; background:#f7fafc;">
        <p style="margin:0 0 8px;">
            <strong><?php esc_html_e('Data', 'fp-experiences'); ?>:</strong>
            <?php echo esc_html((string) ($slot['start_local_date'] ?? '')); ?>
        </p>
        <p style="margin:0 0 8px;">
            <strong><?php esc_html_e('Orario', 'fp-experiences'); ?>:</strong>
            <?php echo esc_html($time_label); ?>
            <?php if (! empty($slot['timezone'])) : ?>
                <span style="color:#556987;">(<?php echo esc_html((string) $slot['timezone']); ?>)</span>
            <?php endif; ?>
        </p>
        <?php if ($meeting_point) : ?>
            <p style="margin:0 0 8px;">
                <strong><?php esc_html_e('Punto di incontro', 'fp-experiences'); ?>:</strong>
                <?php echo esc_html((string) $meeting_point); ?>
            </p>
        <?php endif; ?>
        <?php if (! empty($experience['short_description'])) : ?>
            <p style="margin:0; color:#364152;">
                <?php echo esc_html((string) $experience['short_description']); ?>
            </p>
        <?php endif; ?>
    </div>

    <h2 style="font-size:18px; margin:0 0 12px; color:#0b3d2e;">
        <?php esc_html_e('Riepilogo partecipanti', 'fp-experiences'); ?>
    </h2>
    <table role="presentation" style="width:100%; border-collapse:collapse; margin-bottom:20px;">
        <tbody>
        <?php if ($tickets) : ?>
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
        <?php else : ?>
            <tr>
                <td style="padding:6px 0; border-bottom:1px solid #e2e8f0;" colspan="2">
                    <?php esc_html_e('Nessun partecipante registrato.', 'fp-experiences'); ?>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if ($addons) : ?>
        <h2 style="font-size:18px; margin:0 0 12px; color:#0b3d2e;">
            <?php esc_html_e('Extra selezionati', 'fp-experiences'); ?>
        </h2>
        <ul style="margin:0 0 20px 20px; padding:0; color:#364152;">
            <?php foreach ($addons as $addon) : ?>
                <li>
                    <?php echo esc_html((string) ($addon['label'] ?? $addon['key'] ?? '')); ?>
                    <span style="color:#556987;">× <?php echo esc_html((string) ($addon['quantity'] ?? '1')); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2 style="font-size:18px; margin:0 0 12px; color:#0b3d2e;">
        <?php esc_html_e('Informazioni ordine', 'fp-experiences'); ?>
    </h2>
    <p style="margin:0 0 8px;">
        <strong><?php esc_html_e('Numero ordine', 'fp-experiences'); ?>:</strong>
        <?php echo esc_html((string) ($order['number'] ?? $order['id'] ?? '')); ?>
    </p>
    <p style="margin:0 0 8px;">
        <strong><?php esc_html_e('Totale', 'fp-experiences'); ?>:</strong>
        <?php echo wp_kses_post((string) ($order['total'] ?? '')); ?>
    </p>

    <?php if (! empty($order['notes'])) : ?>
        <p style="margin:12px 0; color:#364152;">
            <strong><?php esc_html_e('Note del cliente', 'fp-experiences'); ?>:</strong>
            <?php echo esc_html((string) $order['notes']); ?>
        </p>
    <?php endif; ?>

    <p style="margin:20px 0;">
        <?php esc_html_e('Troverai allegato un file .ics per aggiungere automaticamente la prenotazione al tuo calendario.', 'fp-experiences'); ?>
        <?php if ($google_link) : ?>
            <a href="<?php echo esc_url((string) $google_link); ?>" style="color:#0b7285; text-decoration:none; margin-left:4px;">
                <?php esc_html_e('Aggiungi a Google Calendar', 'fp-experiences'); ?>
            </a>
        <?php endif; ?>
    </p>

    <div style="margin-top:24px; padding-top:16px; border-top:1px solid #e2e8f0; color:#556987; font-size:13px;">
        <p style="margin:0 0 6px;">
            <?php echo esc_html(sprintf(
                /* translators: %s: customer name. */
                __('Contatto: %s', 'fp-experiences'),
                trim((string) ($customer['name'] ?? ''))
            )); ?>
        </p>
        <?php if (! empty($customer['phone'])) : ?>
            <p style="margin:0 0 6px;">
                <?php echo esc_html(sprintf(
                    /* translators: %s: phone number. */
                    __('Telefono: %s', 'fp-experiences'),
                    (string) $customer['phone']
                )); ?>
            </p>
        <?php endif; ?>
        <p style="margin:0;">
            <?php esc_html_e('Per qualsiasi richiesta rispondi a questa email, saremo felici di aiutarti.', 'fp-experiences'); ?>
        </p>
    </div>
</div>
