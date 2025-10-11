<?php

declare(strict_types=1);

namespace FP_Exp\Migrations;

use FP_Exp\Migrations\Migrations\AddAddonImageMeta;
use FP_Exp\Migrations\Migrations\CreateGiftVoucherTable;
use FP_Exp\Utils\Helpers;
use Throwable;

use function add_action;
use function get_option;
use function is_array;
use function time;
use function update_option;
use function wp_installing;

final class Runner
{
    private const OPTION = 'fp_exp_migrations';

    /**
     * @var array<int, Migration>
     */
    private array $migrations;

    private bool $running = false;

    public function __construct()
    {
        $this->migrations = [
            new AddAddonImageMeta(),
            new CreateGiftVoucherTable(),
        ];
    }

    public function register_hooks(): void
    {
        add_action('init', [$this, 'maybe_run'], 5);
        add_action('admin_init', [$this, 'maybe_run'], 5);
    }

    public function maybe_run(): void
    {
        if ($this->running || wp_installing()) {
            return;
        }

        $this->running = true;

        $completed = get_option(self::OPTION, []);
        if (! is_array($completed)) {
            $completed = [];
        }

        $updated = false;

        foreach ($this->migrations as $migration) {
            $key = $migration->key();

            if (isset($completed[$key])) {
                continue;
            }

            try {
                $migration->run();
                $completed[$key] = time();
                $updated = true;
            } catch (Throwable $exception) {
                Helpers::log_debug('migrations', 'Migration failed', [
                    'key' => $key,
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($updated) {
            update_option(self::OPTION, $completed, false);
        } elseif (! isset($completed['__initialized'])) {
            $completed['__initialized'] = time();
            update_option(self::OPTION, $completed, false);
        }

        $this->running = false;
    }
}
