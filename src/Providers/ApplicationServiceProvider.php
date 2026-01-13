<?php

declare(strict_types=1);

namespace FP_Exp\Providers;

use FP_Exp\Application\Booking\CancelReservationUseCase;
use FP_Exp\Application\Booking\CheckAvailabilityUseCase;
use FP_Exp\Application\Booking\CreateReservationUseCase;
use FP_Exp\Application\Booking\GetExperienceUseCase;
use FP_Exp\Application\Booking\GetReservationUseCase;
use FP_Exp\Application\Booking\GetSlotsUseCase;
use FP_Exp\Application\Booking\ListExperiencesUseCase;
use FP_Exp\Application\Booking\MoveSlotUseCase;
use FP_Exp\Application\Booking\ProcessCheckoutUseCase;
use FP_Exp\Application\Booking\UpdateSlotCapacityUseCase;
use FP_Exp\Application\Booking\UpdateSlotUseCase;
use FP_Exp\Application\Gift\CreateVoucherUseCase;
use FP_Exp\Application\Gift\RedeemVoucherUseCase;
use FP_Exp\Application\Settings\GetSettingsUseCase;
use FP_Exp\Application\Settings\UpdateSettingsUseCase;
use FP_Exp\Core\Container\ContainerInterface;
use FP_Exp\Core\ServiceProvider\AbstractServiceProvider;
use FP_Exp\Domain\Booking\Repositories\ExperienceRepositoryInterface;
use FP_Exp\Domain\Booking\Repositories\ReservationRepositoryInterface;
use FP_Exp\Domain\Booking\Repositories\SlotRepositoryInterface;
use FP_Exp\Domain\Gift\Repositories\VoucherRepositoryInterface;
use FP_Exp\Gift\Services\VoucherCreationService;
use FP_Exp\Gift\Services\VoucherRedemptionService;
use FP_Exp\Gift\Services\VoucherValidationService;
use FP_Exp\Services\Logger\LoggerInterface;
use FP_Exp\Services\Options\OptionsInterface;
use FP_Exp\Services\Sanitization\SanitizerInterface;
use FP_Exp\Services\Validation\ValidatorInterface;

/**
 * Application service provider - registers use cases.
 */
final class ApplicationServiceProvider extends AbstractServiceProvider
{
    /**
     * Register application use cases.
     *
     * @param ContainerInterface $container Container instance
     */
    public function register(ContainerInterface $container): void
    {
        // CheckAvailabilityUseCase
        $container->bind(CheckAvailabilityUseCase::class, static function (ContainerInterface $container): CheckAvailabilityUseCase {
            return new CheckAvailabilityUseCase(
                $container->make(ExperienceRepositoryInterface::class),
                $container->make(SlotRepositoryInterface::class),
                $container->make(ReservationRepositoryInterface::class)
            );
        });

        // CreateReservationUseCase
        $container->bind(CreateReservationUseCase::class, static function (ContainerInterface $container): CreateReservationUseCase {
            return new CreateReservationUseCase(
                $container->make(ExperienceRepositoryInterface::class),
                $container->make(SlotRepositoryInterface::class),
                $container->make(ReservationRepositoryInterface::class),
                $container->make(ValidatorInterface::class)
            );
        });

        // GetSlotsUseCase
        $container->bind(GetSlotsUseCase::class, static function (ContainerInterface $container): GetSlotsUseCase {
            return new GetSlotsUseCase(
                $container->make(SlotRepositoryInterface::class),
                $container->make(ReservationRepositoryInterface::class)
            );
        });

        // UpdateSlotUseCase
        $container->bind(UpdateSlotUseCase::class, static function (ContainerInterface $container): UpdateSlotUseCase {
            return new UpdateSlotUseCase(
                $container->make(SlotRepositoryInterface::class),
                $container->make(ValidatorInterface::class)
            );
        });

        // MoveSlotUseCase
        $container->bind(MoveSlotUseCase::class, static function (ContainerInterface $container): MoveSlotUseCase {
            $useCase = new MoveSlotUseCase(
                $container->make(SlotRepositoryInterface::class),
                $container->make(ValidatorInterface::class)
            );
            
            // Inject logger if available
            if ($container->has(\FP_Exp\Services\Logger\LoggerInterface::class)) {
                $useCase->setLogger($container->make(\FP_Exp\Services\Logger\LoggerInterface::class));
            }
            
            return $useCase;
        });

        // UpdateSlotCapacityUseCase
        $container->bind(UpdateSlotCapacityUseCase::class, static function (ContainerInterface $container): UpdateSlotCapacityUseCase {
            $useCase = new UpdateSlotCapacityUseCase(
                $container->make(SlotRepositoryInterface::class),
                $container->make(ReservationRepositoryInterface::class),
                $container->make(ValidatorInterface::class)
            );
            
            // Inject logger if available
            if ($container->has(\FP_Exp\Services\Logger\LoggerInterface::class)) {
                $useCase->setLogger($container->make(\FP_Exp\Services\Logger\LoggerInterface::class));
            }
            
            return $useCase;
        });

        // GetSettingsUseCase
        $container->bind(GetSettingsUseCase::class, static function (ContainerInterface $container): GetSettingsUseCase {
            return new GetSettingsUseCase(
                $container->make(OptionsInterface::class)
            );
        });

        // UpdateSettingsUseCase
        $container->bind(UpdateSettingsUseCase::class, static function (ContainerInterface $container): UpdateSettingsUseCase {
            return new UpdateSettingsUseCase(
                $container->make(OptionsInterface::class),
                $container->make(SanitizerInterface::class),
                $container->make(ValidatorInterface::class)
            );
        });

        // CreateVoucherUseCase
        $container->bind(CreateVoucherUseCase::class, static function (ContainerInterface $container): CreateVoucherUseCase {
            $useCase = new CreateVoucherUseCase(
                $container->make(VoucherCreationService::class),
                $container->make(VoucherRepositoryInterface::class),
                $container->make(ValidatorInterface::class)
            );
            
            if ($container->has(LoggerInterface::class)) {
                $useCase->setLogger($container->make(LoggerInterface::class));
            }
            
            return $useCase;
        });

        // RedeemVoucherUseCase
        $container->bind(RedeemVoucherUseCase::class, static function (ContainerInterface $container): RedeemVoucherUseCase {
            $useCase = new RedeemVoucherUseCase(
                $container->make(VoucherRedemptionService::class),
                $container->make(VoucherValidationService::class),
                $container->make(VoucherRepositoryInterface::class),
                $container->make(ValidatorInterface::class)
            );
            
            if ($container->has(LoggerInterface::class)) {
                $useCase->setLogger($container->make(LoggerInterface::class));
            }
            
            return $useCase;
        });

        // ProcessCheckoutUseCase
        $container->bind(ProcessCheckoutUseCase::class, static function (ContainerInterface $container): ProcessCheckoutUseCase {
            $useCase = new ProcessCheckoutUseCase(
                $container->make(SlotRepositoryInterface::class),
                $container->make(ReservationRepositoryInterface::class),
                $container->make(ExperienceRepositoryInterface::class),
                $container->make(ValidatorInterface::class)
            );
            
            if ($container->has(LoggerInterface::class)) {
                $useCase->setLogger($container->make(LoggerInterface::class));
            }
            
            return $useCase;
        });

        // GetExperienceUseCase
        $container->bind(GetExperienceUseCase::class, static function (ContainerInterface $container): GetExperienceUseCase {
            $useCase = new GetExperienceUseCase(
                $container->make(ExperienceRepositoryInterface::class)
            );
            
            if ($container->has(\FP_Exp\Services\Cache\CacheInterface::class)) {
                $useCase->setCache($container->make(\FP_Exp\Services\Cache\CacheInterface::class));
            }
            
            return $useCase;
        });

        // CancelReservationUseCase
        $container->bind(CancelReservationUseCase::class, static function (ContainerInterface $container): CancelReservationUseCase {
            $useCase = new CancelReservationUseCase(
                $container->make(ReservationRepositoryInterface::class),
                $container->make(SlotRepositoryInterface::class),
                $container->make(ValidatorInterface::class)
            );
            
            if ($container->has(LoggerInterface::class)) {
                $useCase->setLogger($container->make(LoggerInterface::class));
            }
            
            return $useCase;
        });

        // GetReservationUseCase
        $container->bind(GetReservationUseCase::class, static function (ContainerInterface $container): GetReservationUseCase {
            $useCase = new GetReservationUseCase(
                $container->make(ReservationRepositoryInterface::class),
                $container->make(ExperienceRepositoryInterface::class)
            );
            
            if ($container->has(\FP_Exp\Services\Cache\CacheInterface::class)) {
                $useCase->setCache($container->make(\FP_Exp\Services\Cache\CacheInterface::class));
            }
            
            return $useCase;
        });

        // ListExperiencesUseCase
        $container->bind(ListExperiencesUseCase::class, static function (ContainerInterface $container): ListExperiencesUseCase {
            $useCase = new ListExperiencesUseCase(
                $container->make(ExperienceRepositoryInterface::class)
            );
            
            if ($container->has(\FP_Exp\Services\Cache\CacheInterface::class)) {
                $useCase->setCache($container->make(\FP_Exp\Services\Cache\CacheInterface::class));
            }
            
            return $useCase;
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
            CheckAvailabilityUseCase::class,
            CreateReservationUseCase::class,
            GetSlotsUseCase::class,
            UpdateSlotUseCase::class,
            MoveSlotUseCase::class,
            UpdateSlotCapacityUseCase::class,
            GetSettingsUseCase::class,
            UpdateSettingsUseCase::class,
            CreateVoucherUseCase::class,
            RedeemVoucherUseCase::class,
            ProcessCheckoutUseCase::class,
            GetExperienceUseCase::class,
            CancelReservationUseCase::class,
            GetReservationUseCase::class,
            ListExperiencesUseCase::class,
        ];
    }
}

