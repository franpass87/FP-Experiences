<?php

declare(strict_types=1);

namespace FP_Exp\Migrations;

interface Migration
{
    public function key(): string;

    public function run(): void;
}
