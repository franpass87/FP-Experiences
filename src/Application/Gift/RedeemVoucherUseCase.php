<?php

declare(strict_types=1);

namespace FP_Exp\Application\Gift;

use FP_Exp\Domain\Gift\Repositories\VoucherRepositoryInterface;
use FP_Exp\Gift\Services\VoucherRedemptionService;
use FP_Exp\Gift\Services\VoucherValidationService;
use FP_Exp\Services\Logger\LoggerInterface;
use FP_Exp\Services\Validation\ValidationResult;
use FP_Exp\Services\Validation\ValidatorInterface;
use WP_Error;

/**
 * Use case: Redeem a gift voucher.
 */
final class RedeemVoucherUseCase
{
    private VoucherRedemptionService $redemptionService;
    private VoucherValidationService $validationService;
    private VoucherRepositoryInterface $repository;
    private ValidatorInterface $validator;
    private ?LoggerInterface $logger = null;

    public function __construct(
        VoucherRedemptionService $redemptionService,
        VoucherValidationService $validationService,
        VoucherRepositoryInterface $repository,
        ValidatorInterface $validator
    ) {
        $this->redemptionService = $redemptionService;
        $this->validationService = $validationService;
        $this->repository = $repository;
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
     * Redeem a voucher.
     *
     * @param string $code Voucher code
     * @param array<string, mixed> $data Redemption data
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function execute(string $code, array $data = [])
    {
        // Validate code
        $validation = $this->validator->validate(['code' => $code], ['code' => 'required|string']);
        if (!$validation->isValid()) {
            return new WP_Error(
                'fp_exp_voucher_code_invalid',
                'Invalid voucher code: ' . $validation->getFirstError()
            );
        }

        try {
            // Validate voucher exists and is valid
            $isValid = $this->validationService->validate($code);
            if (!$isValid) {
                return new WP_Error(
                    'fp_exp_voucher_invalid',
                    'Voucher code is invalid or expired'
                );
            }

            // Redeem voucher
            $success = $this->redemptionService->redeem($code, $data);

            if (!$success) {
                return new WP_Error(
                    'fp_exp_voucher_redemption_failed',
                    'Failed to redeem voucher'
                );
            }

            // Log redemption
            if ($this->logger !== null) {
                $this->logger->log('gift', 'Voucher redeemed', [
                    'code' => $code,
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            if ($this->logger !== null) {
                $this->logger->log('gift', 'Voucher redemption error', [
                    'error' => $e->getMessage(),
                    'code' => $code,
                ]);
            }

            return new WP_Error(
                'fp_exp_voucher_redemption_exception',
                'Exception during voucher redemption: ' . $e->getMessage()
            );
        }
    }
}







