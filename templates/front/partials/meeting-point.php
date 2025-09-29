<?php

if (! isset($meeting_point) || ! is_array($meeting_point)) {
    return;
}

$address = $meeting_point['address'] ?? '';
$notes = $meeting_point['notes'] ?? '';
$phone = $meeting_point['phone'] ?? '';
$email = $meeting_point['email'] ?? '';
$opening_hours = $meeting_point['opening_hours'] ?? '';
$lat = isset($meeting_point['lat']) && '' !== $meeting_point['lat'] ? $meeting_point['lat'] : null;
$lng = isset($meeting_point['lng']) && '' !== $meeting_point['lng'] ? $meeting_point['lng'] : null;
?>
<div class="fp-exp-meeting-point" data-fp-meeting-point data-address="<?php echo esc_attr((string) $address); ?>" data-lat="<?php echo esc_attr(null === $lat ? '' : (string) $lat); ?>" data-lng="<?php echo esc_attr(null === $lng ? '' : (string) $lng); ?>">
    <h3 class="fp-exp-meeting-point__title"><?php echo esc_html((string) ($meeting_point['title'] ?? '')); ?></h3>
    <?php if ($address) : ?>
        <p class="fp-exp-meeting-point__address">
            <span><?php echo esc_html((string) $address); ?></span>
            <a href="#" class="fp-exp-meeting-point__map-link" data-fp-map-link target="_blank" rel="noopener">
                <?php esc_html_e('Apri in Maps', 'fp-experiences'); ?>
            </a>
        </p>
    <?php endif; ?>

    <?php if ($opening_hours) : ?>
        <p class="fp-exp-meeting-point__hours">
            <strong><?php esc_html_e('Orari', 'fp-experiences'); ?>:</strong>
            <span><?php echo esc_html((string) $opening_hours); ?></span>
        </p>
    <?php endif; ?>

    <?php if ($phone || $email) : ?>
        <ul class="fp-exp-meeting-point__contacts">
            <?php if ($phone) : ?>
                <li><strong><?php esc_html_e('Tel', 'fp-experiences'); ?>:</strong> <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', (string) $phone)); ?>"><?php echo esc_html((string) $phone); ?></a></li>
            <?php endif; ?>
            <?php if ($email) : ?>
                <li><strong><?php esc_html_e('Email', 'fp-experiences'); ?>:</strong> <a href="mailto:<?php echo esc_attr((string) $email); ?>"><?php echo esc_html((string) $email); ?></a></li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>

    <?php if ($notes) : ?>
        <div class="fp-exp-meeting-point__notes"><?php echo wp_kses_post($notes); ?></div>
    <?php endif; ?>

    <div class="fp-meeting-map" aria-hidden="true"></div>
</div>
