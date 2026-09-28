<?php

declare(strict_types=1);

namespace Horde\Horde\Service;

use Horde\Core\Service\CredentialProvisioningStrategy;
use Horde\Core\Service\CredentialStore;
use Horde\Core\Service\ProvisioningAction;
use Horde\Core\Service\ProvisioningResult;
use Horde\Core\Service\ServicePurpose;
use Horde\SessionHandler\SessionHandler;

/**
 * Provisioning strategy that reads credentials from session and stores them
 * in the credential store for future use.
 *
 * This allows credentials entered at login time to be persisted for service
 * authorization purposes (e.g., IMAP/SMTP passwords from login flow).
 */
class SessionToCredentialStoreProvisioner implements CredentialProvisioningStrategy
{
    public function __construct(
        private readonly SessionHandler $session,
        private readonly CredentialStore $store,
        private readonly string $sessionKeyPrefix = 'password_credential:',
    ) {}

    public function provision(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): ProvisioningResult {
        // Check if credential already exists in store
        $existing = $this->store->find($userId, $providerId, $purpose);
        if ($existing !== null) {
            return new ProvisioningResult(ProvisioningAction::UseSession);
        }

        // Check if credential is available in session
        $sessionKey = $this->sessionKeyPrefix . $userId . ':' . $providerId . ':' . $purpose->identifier();
        $sessionData = $this->session->get($sessionKey);

        if ($sessionData === null || $sessionData === false) {
            return new ProvisioningResult(ProvisioningAction::Unavailable);
        }

        // Credential found in session, copy to store
        try {
            $this->store->store($userId, $providerId, $purpose, $sessionData);
            return new ProvisioningResult(ProvisioningAction::UseSession);
        } catch (\Throwable $e) {
            // Store failed, credential remains session-only
            return new ProvisioningResult(ProvisioningAction::Unavailable);
        }
    }

    /**
     * Helper method to populate session with credentials at login time.
     *
     * Applications should call this when user logs in with username/password.
     */
    public function setSessionCredential(
        string $userId,
        string $providerId,
        ServicePurpose $purpose,
        array|string $credential
    ): void {
        $sessionKey = $this->sessionKeyPrefix . $userId . ':' . $providerId . ':' . $purpose->identifier();
        $this->session->set($sessionKey, $credential);
    }

    /**
     * Clear credential from session.
     */
    public function clearSessionCredential(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): void {
        $sessionKey = $this->sessionKeyPrefix . $userId . ':' . $providerId . ':' . $purpose->identifier();
        $this->session->remove($sessionKey);
    }
}
