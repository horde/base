<?php

declare(strict_types=1);

namespace Horde\Horde\Service;

use Horde\Core\Auth\AuthCredentialStore;
use Horde\Core\Service\CredentialProvisioningStrategy;
use Horde\Core\Service\CredentialStore;
use Horde\Core\Service\ProvisioningAction;
use Horde\Core\Service\ProvisioningResult;
use Horde\Core\Service\ServicePurpose;
use Throwable;

/**
 * Provisioning strategy that reads credentials from the authenticated
 * session and persists them in the credential store for future use.
 *
 * Credentials entered at login time are stored by the login flow via
 * {@see \Horde_Registry::setAuth()}, which writes them through
 * {@see AuthCredentialStore} into the encrypted session slot. This strategy
 * reads them back from that canonical location and copies them into the
 * long-term {@see CredentialStore} so backend services (IMAP/SMTP) can reuse
 * them for service authorization.
 */
class SessionToCredentialStoreProvisioner implements CredentialProvisioningStrategy
{
    public function __construct(
        private readonly AuthCredentialStore $authCredentials,
        private readonly CredentialStore $store,
    ) {}

    public function provision(
        string $userId,
        string $providerId,
        ServicePurpose $purpose
    ): ProvisioningResult {
        // Already persisted in the long-term store: nothing to do.
        $existing = $this->store->find($userId, $providerId, $purpose);
        if ($existing !== null) {
            return new ProvisioningResult(ProvisioningAction::UseSession);
        }

        // Read the credentials captured at login time. These live in the
        // base app's encrypted slot, written by the login flow through
        // Horde_Registry::setAuth() -> AuthCredentialStore::set().
        $credentials = $this->authCredentials->get('horde');
        if (!is_array($credentials) || !isset($credentials['password'])) {
            return new ProvisioningResult(ProvisioningAction::Unavailable);
        }

        // Preserve the username alongside the password when the login flow
        // captured one, so the store holds structured credentials.
        $payload = ['password' => $credentials['password']];
        if (isset($credentials['username'])) {
            $payload['username'] = $credentials['username'];
        } else {
            $payload['username'] = $userId;
        }

        try {
            $this->store->store($userId, $providerId, $purpose, $payload);

            return new ProvisioningResult(ProvisioningAction::UseSession);
        } catch (Throwable $e) {
            // Todo: handle the null store (no real store available) in a more meaningful way.
            // print_r($e); exit;
            // Store failed. The credential remains available only for this
            // session via the auth credential store.
            return new ProvisioningResult(ProvisioningAction::Unavailable);
        }
    }
}
