<?php

namespace App\Modules\Auth\Domain\Entities;

use DateTime;

/**
 * User Entity - Lógica de negocio pura
 * No depende de Laravel ni de la BD
 */
class User
{
    private string $id;

    private string $email;

    private string $name;

    private string $passwordHash;

    private string $role;

    private string $schoolId;

    private string $status;

    private DateTime $createdAt;

    private ?DateTime $updatedAt;

    public function __construct(
        string $id,
        string $email,
        string $name,
        string $passwordHash,
        string $role,
        string $schoolId,
        string $status = 'active'
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->name = $name;
        $this->passwordHash = $passwordHash;
        $this->role = $role;
        $this->schoolId = $schoolId;
        $this->status = $status;
        $this->createdAt = new DateTime;
        $this->updatedAt = null;
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getSchoolId(): string
    {
        return $this->schoolId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    // Métodos de negocio
    public function deactivate(): void
    {
        $this->status = 'inactive';
        $this->updatedAt = new DateTime;
    }

    public function activate(): void
    {
        $this->status = 'active';
        $this->updatedAt = new DateTime;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function changePassword(string $newPasswordHash): void
    {
        $this->passwordHash = $newPasswordHash;
        $this->updatedAt = new DateTime;
    }

    public function updateProfile(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTime;
    }
}
