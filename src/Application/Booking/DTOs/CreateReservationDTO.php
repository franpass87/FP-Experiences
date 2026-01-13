<?php

declare(strict_types=1);

namespace FP_Exp\Application\Booking\DTOs;

/**
 * Data Transfer Object for creating a reservation.
 */
final class CreateReservationDTO
{
    public int $experience_id;
    public int $slot_id;
    public int $quantity;
    public array $participants;
    public ?array $addons;
    public ?string $notes;
    public ?int $voucher_id;

    public function __construct(
        int $experience_id,
        int $slot_id,
        int $quantity,
        array $participants,
        ?array $addons = null,
        ?string $notes = null,
        ?int $voucher_id = null
    ) {
        $this->experience_id = $experience_id;
        $this->slot_id = $slot_id;
        $this->quantity = $quantity;
        $this->participants = $participants;
        $this->addons = $addons;
        $this->notes = $notes;
        $this->voucher_id = $voucher_id;
    }

    /**
     * Create from array data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['experience_id'] ?? 0),
            (int) ($data['slot_id'] ?? 0),
            (int) ($data['quantity'] ?? 1),
            (array) ($data['participants'] ?? []),
            isset($data['addons']) ? (array) $data['addons'] : null,
            isset($data['notes']) ? (string) $data['notes'] : null,
            isset($data['voucher_id']) ? (int) $data['voucher_id'] : null
        );
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'experience_id' => $this->experience_id,
            'slot_id' => $this->slot_id,
            'quantity' => $this->quantity,
            'participants' => $this->participants,
            'addons' => $this->addons,
            'notes' => $this->notes,
            'voucher_id' => $this->voucher_id,
        ];
    }
}







