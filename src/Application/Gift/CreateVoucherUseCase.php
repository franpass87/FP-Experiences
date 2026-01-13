<?php

declare(strict_types=1);

namespace FP_Exp\Application\Gift;

use FP_Exp\Domain\Gift\Repositories\VoucherRepositoryInterface;
use FP_Exp\Gift\Services\VoucherCreationService;
use FP_Exp\Services\Logger\LoggerInterface;
use FP_Exp\Services\Validation\ValidationResult;
use FP_Exp\Services\Validation\ValidatorInterface;
use WP_Error;

/**
 * Use case: Create a gift voucher.
 */
final class CreateVoucherUseCase
{
    private VoucherCreationService $creationService;
    private VoucherRepositoryInterface $repository;
    private ValidatorInterface $validator;
    private ?LoggerInterface $logger = null;

    public function __construct(
        VoucherCreationService $creationService,
        VoucherRepositoryInterface $repository,
        ValidatorInterface $validator
    ) {
        $this->creationService = $creationService;
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
     * Create a voucher.
     *
     * @param array<string, mixed> $data Voucher data
     * @return int|WP_Error Voucher ID on success, WP_Error on failure
     */
    public function execute(array $data)
    {
        // Validate input
        $validation = $this->validateInput($data);
        if (!$validation->isValid()) {
            return new WP_Error(
                'fp_exp_voucher_validation_failed',
                'Voucher validation failed: ' . $validation->getFirstError(),
                ['errors' => $validation->getErrors()]
            );
        }

        try {
            // Create voucher using creation service
            $voucher_id = $this->creationService->create($data);

            if ($voucher_id <= 0) {
                return new WP_Error(
                    'fp_exp_voucher_creation_failed',
                    'Failed to create voucher'
                );
            }

            // Log creation
            if ($this->logger !== null) {
                $this->logger->log('gift', 'Voucher created', [
                    'voucher_id' => $voucher_id,
                    'experience_id' => $data['experience_id'] ?? 0,
                ]);
            }

            return $voucher_id;
        } catch (\Throwable $e) {
            if ($this->logger !== null) {
                $this->logger->log('gift', 'Voucher creation error', [
                    'error' => $e->getMessage(),
                    'data' => $data,
                ]);
            }

            return new WP_Error(
                'fp_exp_voucher_creation_exception',
                'Exception during voucher creation: ' . $e->getMessage()
            );
        }
    }

    /**
     * Validate voucher input data.
     *
     * @param array<string, mixed> $data Voucher data
     * @return ValidationResult Validation result
     */
    private function validateInput(array $data): ValidationResult
    {
        $rules = [
            'experience_id' => 'required|integer|min:1',
            'quantity' => 'required|integer|min:1',
            'value' => 'required|numeric|min:0',
            'currency' => 'required|string',
        ];

        return $this->validator->validate($data, $rules);
    }
}







