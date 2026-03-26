<?php

namespace App\Services\LanCore;

/**
 * Represents resolved user data from the LanCore integration API.
 *
 * Scope-dependent fields:
 *  - user:read  → id, username, locale, avatar, created_at (always present)
 *  - user:email → email (nullable if scope not granted)
 *  - user:roles → roles (nullable if scope not granted)
 *
 * @phpstan-type LanCoreUserData array{
 *     id: int,
 *     username: string,
 *     locale: string|null,
 *     avatar: string|null,
 *     created_at: string|null,
 *     email: string|null,
 *     roles: list<string>|null,
 * }
 */
readonly class LanCoreUser
{
    /**
     * @param  list<string>|null  $roles
     */
    public function __construct(
        public int $id,
        public string $username,
        public ?string $locale = null,
        public ?string $avatar = null,
        public ?string $createdAt = null,
        public ?string $email = null,
        /** @var list<string>|null */
        public ?array $roles = null,
    ) {}

    /**
     * @param  LanCoreUserData  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            username: (string) $data['username'],
            locale: $data['locale'] ?? null,
            avatar: $data['avatar'] ?? null,
            createdAt: $data['created_at'] ?? null,
            email: $data['email'] ?? null,
            roles: $data['roles'] ?? null,
        );
    }

    public function isValid(): bool
    {
        return $this->id > 0
            && $this->username !== '';
    }
}
