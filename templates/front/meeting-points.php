<?php
/** @var array $primary */
/** @var array $alternatives */
/** @var string $scope_class */
/** @var bool $embedded */

if (! isset($primary) || ! is_array($primary)) {
    return;
}

$embedded = $embedded ?? false;
$wrapper_classes = trim('fp-exp-meeting-points ' . ($scope_class ?? ''));
$wrapper_tag = $embedded ? 'div' : 'section';
$wrapper_classes_final = $embedded ? $wrapper_classes : $wrapper_classes . ' fp-exp-section';
?>
<<?php echo $wrapper_tag; ?> class="<?php echo esc_attr($wrapper_classes_final); ?>" data-fp-shortcode="meeting-points"<?php if (!$embedded) : ?> id="fp-exp-section-meeting" data-fp-section="meeting"<?php endif; ?>>
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
</<?php echo $wrapper_tag; ?>>
