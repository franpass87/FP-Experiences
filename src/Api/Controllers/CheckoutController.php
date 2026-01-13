<?php

declare(strict_types=1);

namespace FP_Exp\Api\Controllers;

use FP_Exp\Application\Booking\DTOs\CreateReservationDTO;
use FP_Exp\Application\Booking\ProcessCheckoutUseCase;
use FP_Exp\Core\Container\ContainerInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST controller for checkout operations.
 */
final class CheckoutController
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Handle checkout request.
     *
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function handleCheckout(WP_REST_Request $request)
    {
        $cart = $request->get_json_params()['cart'] ?? [];
        $payload = $request->get_json_params()['payload'] ?? [];

        if (empty($cart)) {
            return new \WP_Error(
                'fp_exp_empty_cart',
                'Cart is empty',
                ['status' => 400]
            );
        }

        try {
            $useCase = $this->container->make(ProcessCheckoutUseCase::class);
            $result = $useCase->execute($cart, $payload);

            if (is_wp_error($result)) {
                return $result;
            }

            return new \WP_REST_Response([
                'success' => true,
                'order_id' => $result,
            ], 200);
        } catch (\Throwable $e) {
            return new \WP_Error(
                'fp_exp_checkout_exception',
                'Checkout failed: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Permission callback for checkout.
     *
     * @param WP_REST_Request $request REST request
     * @return bool True if allowed
     */
    public function checkPermission(WP_REST_Request $request): bool
    {
        // Check nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return false;
        }

        // Check rate limiting if needed
        return true;
    }
}







