<?php

declare(strict_types=1);

namespace FP_Exp\MeetingPoints;

use function is_admin;

final class Manager
{
    private MeetingPointCPT $cpt;

    private RestController $rest_controller;

    private ?MeetingPointMetaBoxes $meta_boxes = null;

    private ?ExperienceMetaBox $experience_meta_box = null;

    private ?MeetingPointImporter $importer = null;

    public function __construct()
    {
        $this->cpt = new MeetingPointCPT();
        $this->rest_controller = new RestController();

        if (is_admin()) {
            $this->meta_boxes = new MeetingPointMetaBoxes();
            $this->experience_meta_box = new ExperienceMetaBox();
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

        if ($this->experience_meta_box instanceof ExperienceMetaBox) {
            $this->experience_meta_box->register_hooks();
        }

        if ($this->importer instanceof MeetingPointImporter) {
            $this->importer->register_hooks();
        }
    }
}
