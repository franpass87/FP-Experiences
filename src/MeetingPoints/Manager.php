<?php

declare(strict_types=1);

namespace FP_Exp\MeetingPoints;

use FP_Exp\Core\Hook\HookableInterface;

use function is_admin;

final class Manager implements HookableInterface
{
    private MeetingPointCPT $cpt;

    private RestController $rest_controller;

    private ?MeetingPointMetaBoxes $meta_boxes = null;

    private ?MeetingPointImporter $importer = null;

    public function __construct()
    {
        $this->cpt = new MeetingPointCPT();
        $this->rest_controller = new RestController();

        if (is_admin()) {
            $this->meta_boxes = new MeetingPointMetaBoxes();
            $this->importer = new MeetingPointImporter();
        }
    }

    public function register_hooks(): void
    {
        $this->cpt->register_hooks();
        $this->rest_controller->register_hooks();

        if ($this->meta_boxes instanceof MeetingPointMetaBoxes) {
            $this->meta_boxes->register_hooks();
        }

        if ($this->importer instanceof MeetingPointImporter) {
            $this->importer->register_hooks();
        }
    }
}
