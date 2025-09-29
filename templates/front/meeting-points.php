<?php
/** @var array $primary */
/** @var array $alternatives */
/** @var string $scope_class */

if (! isset($primary) || ! is_array($primary)) {
    return;
}

$wrapper_classes = trim('fp-exp-meeting-points ' . ($scope_class ?? ''));
?>
<section class="<?php echo esc_attr($wrapper_classes); ?>" data-fp-shortcode="meeting-points">
    <div class="fp-exp-meeting-points__primary">
        <?php
        $meeting_point = $primary;
        include __DIR__ . '/partials/meeting-point.php';
        ?>
    </div>

    <?php if (! empty($alternatives)) : ?>
        <details class="fp-exp-meeting-points__alternatives">
            <summary><?php esc_html_e('Punti di ritrovo alternativi', 'fp-experiences'); ?></summary>
            <div class="fp-exp-meeting-points__list">
                <?php foreach ($alternatives as $alternative) : ?>
                    <?php
                    $meeting_point = $alternative;
                    include __DIR__ . '/partials/meeting-point.php';
                    ?>
                <?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>
</section>
