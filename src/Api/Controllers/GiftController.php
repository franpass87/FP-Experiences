<?php

declare(strict_types=1);

namespace FP_Exp\Api\Controllers;

use FP_Exp\Api\Middleware\ErrorHandlingMiddleware;
use FP_Exp\Gift\VoucherManager;
use FP_Exp\Utils\Helpers;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function is_wp_error;
use function rest_ensure_response;
use function sanitize_text_field;

/**
 * Controller for gift voucher REST API endpoints.
 */
final class GiftController
{
    private VoucherManager $voucher_manager;

    public function __construct(VoucherManager $voucher_manager)
    {
        $this->voucher_manager = $voucher_manager;
    }

    /**
     * Purchase a gift voucher.
     */
    public function purchase(WP_REST_Request $request): WP_REST_Response
    {
        if (! Helpers::gift_enabled()) {
            return ErrorHandlingMiddleware::badRequest(
                esc_html__('Gift vouchers are disabled.', 'fp-experiences')
            );
        }

        $payload = [
            'experience_id' => $request->get_param('experience_id'),
            'quantity' => $request->get_param('quantity'),
            'addons' => $request->get_param('addons'),
            'purchaser' => $request->get_param('purchaser'),
            'recipient' => $request->get_param('recipient'),
            'message' => $request->get_param('message'),
            'delivery' => $request->get_param('delivery'),
        ];

        $result = $this->voucher_manager->create_purchase($payload);

        if (is_wp_error($result)) {
            $result->add_data(['status' => 400]);

            return rest_ensure_response($result);
        }

        return ErrorHandlingMiddleware::success($result);
    }

    /**
     * Get gift voucher by code.
     */
    public function getVoucher(WP_REST_Request $request): WP_REST_Response
    {
        $code = sanitize_text_field((string) $request->get_param('code'));

        if (empty($code)) {
            return ErrorHandlingMiddleware::badRequest(
                esc_html__('Voucher code is required.', 'fp-experiences')
            );
        }

        $result = $this->voucher_manager->get_voucher_by_code($code);

        if (is_wp_error($result)) {
            $result->add_data(['status' => 404]);

            return rest_ensure_response($result);
        }

        return ErrorHandlingMiddleware::success($result);
    }

    /**
     * Redeem a gift voucher.
     */
    public function redeem(WP_REST_Request $request): WP_REST_Response
    {
        $code = sanitize_text_field((string) $request->get_param('code'));

        if (empty($code)) {
            return ErrorHandlingMiddleware::badRequest(
                esc_html__('Voucher code is required.', 'fp-experiences')
            );
        }

        $payload = [
            'slot_id' => $request->get_param('slot_id'),
        ];

        $result = $this->voucher_manager->redeem_voucher($code, $payload);

        if (is_wp_error($result)) {
            $result->add_data(['status' => 400]);

            return rest_ensure_response($result);
        }

        return ErrorHandlingMiddleware::success($result);
    }
}















