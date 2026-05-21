<?php

namespace App\Modules\Auth\Domain\ValueObjects;

/**
 * Email ValueObject
 * Encapsula la validación y lógica de email
 */
class Email
{
    private string $value;

    private function __construct(string $value)
    {
        $this->validate($value);
        $this->value = strtolower(trim($value));
    }

    public static function create(string $value): self
    {
        return new self($value);
    }

    private function validate(string $value): void
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Email no puede estar vacío');
        }

        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Email inválido: {$value}");
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
