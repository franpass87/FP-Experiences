<?php

declare(strict_types=1);

namespace FP_Exp\Application\Booking;

use FP_Exp\Application\Booking\DTOs\CreateReservationDTO;
use FP_Exp\Domain\Booking\Repositories\ExperienceRepositoryInterface;
use FP_Exp\Domain\Booking\Repositories\ReservationRepositoryInterface;
use FP_Exp\Domain\Booking\Repositories\SlotRepositoryInterface;
use FP_Exp\Services\Logger\LoggerInterface;
use FP_Exp\Services\Validation\ValidationResult;
use FP_Exp\Services\Validation\ValidatorInterface;
use WP_Error;

/**
 * Use case: Process checkout and create order with reservations.
 */
final class ProcessCheckoutUseCase
{
    private SlotRepositoryInterface $slotRepository;
    private ReservationRepositoryInterface $reservationRepository;
    private ExperienceRepositoryInterface $experienceRepository;
    private ValidatorInterface $validator;
    private ?LoggerInterface $logger = null;

    public function __construct(
        SlotRepositoryInterface $slotRepository,
        ReservationRepositoryInterface $reservationRepository,
        ExperienceRepositoryInterface $experienceRepository,
        ValidatorInterface $validator
    ) {
        $this->slotRepository = $slotRepository;
        $this->reservationRepository = $reservationRepository;
        $this->experienceRepository = $experienceRepository;
        $this->validator = $validator;
    }

    /**
     * Set logger (optional).
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Process checkout.
     *
     * @param array<string, mixed> $cart Cart data
     * @param array<string, mixed> $payload Checkout payload (customer data, payment, etc.)
     * @return int|WP_Error Order ID on success, WP_Error on failure
     */
    public function execute(array $cart, array $payload)
    {
        // Validate cart
        $validation = $this->validateCart($cart);
        if (!$validation->isValid()) {
            return new WP_Error(
                'fp_exp_checkout_validation_failed',
                'Cart validation failed: ' . $validation->getFirstError(),
                ['errors' => $validation->getErrors()]
            );
        }

        // Validate payload
        $payloadValidation = $this->validatePayload($payload);
        if (!$payloadValidation->isValid()) {
            return new WP_Error(
                'fp_exp_checkout_payload_invalid',
                'Checkout payload invalid: ' . $payloadValidation->getFirstError(),
                ['errors' => $payloadValidation->getErrors()]
            );
        }

        try {
            // Create WooCommerce order
            $order_id = $this->createOrder($cart, $payload);
            
            if (is_wp_error($order_id)) {
                return $order_id;
            }

            // Create reservations for each cart item
            foreach ($cart['items'] ?? [] as $item) {
                $dto = CreateReservationDTO::fromArray([
                    'experience_id' => $item['experience_id'] ?? 0,
                    'slot_id' => $item['slot_id'] ?? 0,
                    'quantity' => $item['quantity'] ?? 1,
                    'participants' => $item['participants'] ?? [],
                    'addons' => $item['addons'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);

                $createReservation = new CreateReservationUseCase(
                    $this->experienceRepository,
                    $this->slotRepository,
                    $this->reservationRepository,
                    $this->validator
                );

                if ($this->logger !== null) {
                    $createReservation->setLogger($this->logger);
                }

                $reservation_result = $createReservation->execute($dto);

                if (is_wp_error($reservation_result)) {
                    // Log error but continue with other items
                    if ($this->logger !== null) {
                        $this->logger->log('checkout', 'Failed to create reservation', [
                            'order_id' => $order_id,
                            'item' => $item,
                            'error' => $reservation_result->get_error_message(),
                        ]);
                    }
                }
            }

            // Log success
            if ($this->logger !== null) {
                $this->logger->log('checkout', 'Checkout processed successfully', [
                    'order_id' => $order_id,
                    'items_count' => count($cart['items'] ?? []),
                ]);
            }

            return $order_id;
        } catch (\Throwable $e) {
            if ($this->logger !== null) {
                $this->logger->log('checkout', 'Checkout exception', [
                    'error' => $e->getMessage(),
                    'cart' => $cart,
                ]);
            }

            return new WP_Error(
                'fp_exp_checkout_exception',
                'Exception during checkout: ' . $e->getMessage()
            );
        }
    }

    /**
     * Create WooCommerce order.
     *
     * @param array<string, mixed> $cart Cart data
     * @param array<string, mixed> $payload Checkout payload
     * @return int|WP_Error Order ID or error
     */
    private function createOrder(array $cart, array $payload)
    {
        if (!function_exists('wc_create_order')) {
            return new WP_Error(
                'fp_exp_missing_wc',
                'WooCommerce is required to process checkout.'
            );
        }

        try {
            $order = \wc_create_order(['status' => 'pending']);

            if (is_wp_error($order) || !$order) {
                return new WP_Error(
                    'fp_exp_order_failed',
                    'Failed to create order.'
                );
            }

            // Add items to order
            foreach ($cart['items'] ?? [] as $item) {
                // Add order item logic here
                // This should delegate to Orders service
            }

            // Set customer data
            if (isset($payload['customer'])) {
                // Set billing/shipping from payload
            }

            // Calculate totals
            $order->calculate_totals();
            $order->save();

            return $order->get_id();
        } catch (\Throwable $e) {
            return new WP_Error(
                'fp_exp_order_exception',
                'Exception creating order: ' . $e->getMessage()
            );
        }
    }

    /**
     * Validate cart data.
     *
     * @param array<string, mixed> $cart Cart data
     * @return ValidationResult
     */
    private function validateCart(array $cart): ValidationResult
    {
        $rules = [
            'items' => 'required|array',
        ];

        return $this->validator->validate($cart, $rules);
    }

    /**
     * Validate checkout payload.
     *
     * @param array<string, mixed> $payload Payload data
     * @return ValidationResult
     */
    private function validatePayload(array $payload): ValidationResult
    {
        $rules = [
            'customer' => 'required|array',
            'customer.email' => 'required|email',
        ];

        return $this->validator->validate($payload, $rules);
    }
}







