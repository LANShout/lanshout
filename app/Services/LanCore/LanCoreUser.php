<?php

namespace App\Services\LanCore;

/**
 * @phpstan-type LanCoreUserData array{
 *     id: int,
 *     username: string,
 *     display_name: string,
 *     email: string,
 *     avatar_url: string|null,
 *     locale: string|null,
 * }
 */
readonly class LanCoreUser
{
    public function __construct(
        public int $id,
        public string $username,
        public string $displayName,
        public string $email,
        public ?string $avatarUrl = null,
        public ?string $locale = null,
    ) {}

    /**
     * @param  LanCoreUserData  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            username: (string) $data['username'],
            displayName: (string) ($data['display_name'] ?? $data['username']),
            email: (string) $data['email'],
            avatarUrl: $data['avatar_url'] ?? null,
            locale: $data['locale'] ?? null,
        );
    }

    public function isValid(): bool
    {
        return $this->id > 0
            && $this->username !== ''
            && $this->email !== '';
    }
}
