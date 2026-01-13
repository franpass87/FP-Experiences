<?php

declare(strict_types=1);

namespace FP_Exp\Application\Booking\DTOs;

/**
 * Data Transfer Object for updating a slot.
 */
final class UpdateSlotDTO
{
    public ?int $experience_id;
    public ?string $start_datetime;
    public ?string $end_datetime;
    public ?int $capacity;
    public ?float $price;
    public ?string $status;

    public function __construct(
        ?int $experience_id = null,
        ?string $start_datetime = null,
        ?string $end_datetime = null,
        ?int $capacity = null,
        ?float $price = null,
        ?string $status = null
    ) {
        $this->experience_id = $experience_id;
        $this->start_datetime = $start_datetime;
        $this->end_datetime = $end_datetime;
        $this->capacity = $capacity;
        $this->price = $price;
        $this->status = $status;
    }

    /**
     * Create from array data.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['experience_id']) ? (int) $data['experience_id'] : null,
            isset($data['start_datetime']) ? (string) $data['start_datetime'] : null,
            isset($data['end_datetime']) ? (string) $data['end_datetime'] : null,
            isset($data['capacity']) ? (int) $data['capacity'] : null,
            isset($data['price']) ? (float) $data['price'] : null,
            isset($data['status']) ? (string) $data['status'] : null
        );
    }

    /**
     * Convert to array (only non-null values).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->experience_id !== null) {
            $result['experience_id'] = $this->experience_id;
        }
        if ($this->start_datetime !== null) {
            $result['start_datetime'] = $this->start_datetime;
        }
        if ($this->end_datetime !== null) {
            $result['end_datetime'] = $this->end_datetime;
        }
        if ($this->capacity !== null) {
            $result['capacity'] = $this->capacity;
        }
        if ($this->price !== null) {
            $result['price'] = $this->price;
        }
        if ($this->status !== null) {
            $result['status'] = $this->status;
        }

        return $result;
    }
}







