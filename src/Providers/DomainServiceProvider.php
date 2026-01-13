<?php

declare(strict_types=1);

namespace FP_Exp\Providers;

use FP_Exp\Core\Container\ContainerInterface;
use FP_Exp\Core\ServiceProvider\AbstractServiceProvider;
use FP_Exp\Domain\Booking\Repositories\ExperienceRepositoryInterface;
use FP_Exp\Domain\Booking\Repositories\ReservationRepositoryInterface;
use FP_Exp\Domain\Booking\Repositories\SlotRepositoryInterface;
use FP_Exp\Domain\Gift\Repositories\VoucherRepositoryInterface;
use FP_Exp\Gift\Repository\VoucherRepository;
use FP_Exp\Gift\Services\VoucherCreationService;
use FP_Exp\Gift\Services\VoucherRedemptionService;
use FP_Exp\Gift\Services\VoucherValidationService;
use FP_Exp\Infrastructure\Database\ExperienceRepository;
use FP_Exp\Infrastructure\Database\ReservationRepository;
use FP_Exp\Infrastructure\Database\SlotRepository;
use FP_Exp\Services\Database\DatabaseInterface;

/**
 * Domain service provider - registers domain repositories and services.
 */
final class DomainServiceProvider extends AbstractServiceProvider
{
    /**
     * Register domain services and repositories.
     *
     * @param ContainerInterface $container Container instance
     */
    public function register(ContainerInterface $container): void
    {
        // Booking Domain Repositories
        $container->singleton(SlotRepositoryInterface::class, static function (ContainerInterface $container): SlotRepository {
            return new SlotRepository($container->make(DatabaseInterface::class));
        });

        $container->singleton(ReservationRepositoryInterface::class, static function (ContainerInterface $container): ReservationRepository {
            return new ReservationRepository($container->make(DatabaseInterface::class));
        });

        $container->singleton(ExperienceRepositoryInterface::class, ExperienceRepository::class);

        // Voucher Repository
        $container->singleton(VoucherRepositoryInterface::class, VoucherRepository::class);

        // Voucher Services
        $container->singleton(VoucherCreationService::class, static function (ContainerInterface $container): VoucherCreationService {
            return new VoucherCreationService(
                $container->make(VoucherRepositoryInterface::class)
            );
        });

        $container->singleton(VoucherRedemptionService::class, static function (ContainerInterface $container): VoucherRedemptionService {
            return new VoucherRedemptionService(
                $container->make(VoucherRepositoryInterface::class)
            );
        });

        $container->singleton(VoucherValidationService::class, static function (ContainerInterface $container): VoucherValidationService {
            return new VoucherValidationService(
                $container->make(VoucherRepositoryInterface::class)
            );
        });
    }

    /**
     * Get list of services provided.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            SlotRepositoryInterface::class,
            ReservationRepositoryInterface::class,
            ExperienceRepositoryInterface::class,
            VoucherRepositoryInterface::class,
            VoucherCreationService::class,
            VoucherRedemptionService::class,
            VoucherValidationService::class,
        ];
    }
}










