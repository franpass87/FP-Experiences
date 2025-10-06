<?php

declare(strict_types=1);

namespace FP_Exp\Migrations\Migrations;

use FP_Exp\Gift\VoucherCPT;
use FP_Exp\Gift\VoucherTable;
use FP_Exp\Migrations\Migration;
use WP_Query;

use function absint;
use function get_post_meta;
use function get_post_modified_time;
use function get_post_time;
use function is_array;
use function sanitize_key;
use function sanitize_text_field;
use function wp_reset_postdata;

final class CreateGiftVoucherTable implements Migration
{
    public function key(): string
    {
        return '20241001_gift_voucher_table';
    }

    public function run(): void
    {
        VoucherTable::create_table();

        $paged = 1;
        $per_page = 50;

        do {
            $query = new WP_Query([
                'post_type' => VoucherCPT::POST_TYPE,
                'post_status' => 'any',
                'fields' => 'ids',
                'posts_per_page' => $per_page,
                'paged' => $paged,
                'no_found_rows' => true,
            ]);

            $voucher_ids = $query->posts;

            if (! is_array($voucher_ids) || empty($voucher_ids)) {
                wp_reset_postdata();
                break;
            }

            foreach ($voucher_ids as $voucher_id) {
                $voucher_id = absint($voucher_id);

                if ($voucher_id <= 0) {
                    continue;
                }

                $code = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_code', true));

                if ('' === $code) {
                    continue;
                }

                $status = sanitize_key((string) get_post_meta($voucher_id, '_fp_exp_gift_status', true));
                if ('' === $status) {
                    $status = 'pending';
                }

                $experience_id = absint((string) get_post_meta($voucher_id, '_fp_exp_gift_experience_id', true));
                $valid_until = absint((string) get_post_meta($voucher_id, '_fp_exp_gift_valid_until', true));
                $value = (float) get_post_meta($voucher_id, '_fp_exp_gift_value', true);
                $currency = sanitize_text_field((string) get_post_meta($voucher_id, '_fp_exp_gift_currency', true));
                $created = (int) get_post_time('U', true, $voucher_id, true);
                $modified = (int) get_post_modified_time('U', true, $voucher_id, true);

                VoucherTable::upsert([
                    'voucher_id' => $voucher_id,
                    'code' => $code,
                    'status' => $status,
                    'experience_id' => $experience_id,
                    'valid_until' => $valid_until,
                    'value' => $value,
                    'currency' => $currency,
                    'created_at' => $created,
                    'updated_at' => $modified ?: $created,
                ]);
            }

            wp_reset_postdata();
            $paged++;
        } while (! empty($voucher_ids) && count($voucher_ids) === $per_page);
    }
}
