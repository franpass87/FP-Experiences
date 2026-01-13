<?php

declare(strict_types=1);

namespace FP_Exp\Application\Gift\DTOs;

/**
 * Data Transfer Object for creating a gift voucher.
 */
final class CreateVoucherDTO
{
    public int $experience_id;
    public int $quantity;
    public float $value;
    public string $currency;
    public array $purchaser;
    public ?array $recipient;
    public ?string $message;
    public ?array $delivery;
    public ?array $addons;

    public function __construct(
        int $experience_id,
        int $quantity,
        float $value,
        string $currency,
        array $purchaser,
        ?array $recipient = null,
        ?string $message = null,
        ?array $delivery = null,
        ?array $addons = null
    ) {
        $this->experience_id = $experience_id;
        $this->quantity = $quantity;
        $this->value = $value;
        $this->currency = $currency;
        $this->purchaser = $purchaser;
        $this->recipient = $recipient;
        $this->message = $message;
        $this->delivery = $delivery;
        $this->addons = $addons;
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
            (int) ($data['quantity'] ?? 1),
            (float) ($data['value'] ?? 0.0),
            (string) ($data['currency'] ?? 'EUR'),
            (array) ($data['purchaser'] ?? []),
            isset($data['recipient']) ? (array) $data['recipient'] : null,
            isset($data['message']) ? (string) $data['message'] : null,
            isset($data['delivery']) ? (array) $data['delivery'] : null,
            isset($data['addons']) ? (array) $data['addons'] : null
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
            'quantity' => $this->quantity,
            'value' => $this->value,
            'currency' => $this->currency,
            'purchaser' => $this->purchaser,
            'recipient' => $this->recipient,
            'message' => $this->message,
            'delivery' => $this->delivery,
            'addons' => $this->addons,
        ];
    }
}







