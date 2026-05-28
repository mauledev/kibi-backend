<?php

namespace App\Modules\Auth\Domain\Contracts;

use App\Modules\Auth\Domain\Entities\User;

interface UserRepositoryInterface
{
    /** Find a user by their internal ID within the current scope. */
    public function findById(int $id): ?User;

    /** Find a user by their public UUID within the current scope. */
    public function findByUuid(string $uuid): ?User;

    /** Find a user by email within the current scope. */
    public function findByEmail(string $email): ?User;

    /** Find a user by their Google OAuth provider ID within the current scope. */
    public function findByGoogleId(string $googleId): ?User;

    /** Find a user by their Microsoft OAuth provider ID within the current scope. */
    public function findByMicrosoftId(string $microsoftId): ?User;

    /** Persist a new user and return the domain entity. */
    public function save(User $user): User;

    /** Update mutable fields of an existing user and return the updated entity. */
    public function update(User $user): User;

    /** Soft-delete a user by their internal ID. Returns true when deleted. */
    public function delete(int $id): bool;
}
