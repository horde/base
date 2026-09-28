<?php

declare(strict_types=1);

namespace Horde\Horde\Service;

use Horde\Core\Service\PasswordCredential;
use Horde\Core\Service\ServicePurpose;

/** Concrete implementation of PasswordCredential. */
class HordePasswordCredential implements PasswordCredential
{
    public function __construct(
        private readonly string $credentialId,
        private readonly string $userId,
        private readonly string $providerId,
        private readonly ServicePurpose $purpose,
        private readonly array|string $data,
        private readonly int $createdAt,
        private readonly int $updatedAt,
    ) {}

    public function credentialId(): string
    {
        return $this->credentialId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function providerId(): string
    {
        return $this->providerId;
    }

    public function purpose(): ServicePurpose
    {
        return $this->purpose;
    }

    public function asStructured(): array
    {
        if (is_array($this->data)) {
            return $this->data;
        }

        // If stored as string but accessed as structured, attempt JSON decode
        if (is_string($this->data)) {
            $decoded = json_decode($this->data, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        throw new \RuntimeException('Credential data is not structured');
    }

    public function asOpaque(): string
    {
        if (is_string($this->data)) {
            return $this->data;
        }

        // If stored as array, encode as JSON
        if (is_array($this->data)) {
            return json_encode($this->data, JSON_THROW_ON_ERROR);
        }

        throw new \RuntimeException('Cannot convert credential to opaque string');
    }

    public function asBearerToken(): string
    {
        // If opaque string, return as-is
        if (is_string($this->data)) {
            return $this->data;
        }

        // If structured with 'bearer' key
        if (is_array($this->data) && isset($this->data['bearer'])) {
            return $this->data['bearer'];
        }

        // If structured with 'token' key (API key)
        if (is_array($this->data) && isset($this->data['token'])) {
            return $this->data['token'];
        }

        throw new \RuntimeException('Credential does not contain bearer token');
    }

    public function asBasicAuth(): string
    {
        $structured = $this->asStructured();

        if (!isset($structured['username']) || !isset($structured['password'])) {
            throw new \RuntimeException('Credential does not contain username/password');
        }

        return base64_encode($structured['username'] . ':' . $structured['password']);
    }

    public function createdAt(): int
    {
        return $this->createdAt;
    }

    public function updatedAt(): int
    {
        return $this->updatedAt;
    }
}
