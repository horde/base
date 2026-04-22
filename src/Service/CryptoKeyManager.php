<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Horde
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Horde\Service;

use Horde\Exception\HordeRuntimeException;

class CryptoKeyManager
{
    /**
     * Ensures that an RSA private key exists at the specified path.
     *
     * If the file does not exist, a new RSA keypair will be generated and saved to the file.
     * The file will be created with permissions 0600.
     *
     * This is needed by the OAuth2 Server to sign access tokens and authorization codes.
     * The same key can be used across multiple instances of the server if they have access to the same file path.
     *
     * @param string $path The path to the RSA private key file.
     * @param int $bits The number of bits for the RSA key.
     * @throws HordeRuntimeException If the key cannot be generated or saved.
     */
    public function ensureRsaKeyExists(string $path, int $bits = 2048): void
    {
        if (file_exists($path)) {
            return;
        }

        $this->ensureDirectory(dirname($path));

        $key = openssl_pkey_new([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($key === false) {
            throw new HordeRuntimeException(
                'Failed to generate RSA keypair: ' . openssl_error_string()
            );
        }

        $pem = '';
        if (!openssl_pkey_export($key, $pem)) {
            throw new HordeRuntimeException(
                'Failed to export RSA private key: ' . openssl_error_string()
            );
        }

        $this->writeSecureFile($path, $pem);
    }

    /**
     * Ensures that a secret key exists at the specified path.
     *
     * If the file does not exist, a new secret key will be generated and saved to the file.
     * The file will be created with permissions 0600.
     *
     * This is needed by Horde's internal "session" JWT handler to sign id tokens and refresh tokens.
     *
     * @param string $path The path to the secret key file.
     * @param int $bytes The number of bytes for the secret key.
     * @throws HordeRuntimeException If the key cannot be generated or saved.
     */
    public function ensureSecretExists(string $path, int $bytes = 32): void
    {
        if (file_exists($path)) {
            return;
        }

        $this->ensureDirectory(dirname($path));

        $secret = base64_encode(random_bytes($bytes));

        $this->writeSecureFile($path, $secret);
    }

    /**
     * TODO: Replace with something in Horde\Util or the likes
     */
    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0o700, true)) {
            throw new HordeRuntimeException(
                "Cannot create directory: {$dir}"
            );
        }
    }

    private function writeSecureFile(string $path, string $content): void
    {
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new HordeRuntimeException(
                "Failed to write to: {$path}"
            );
        }

        chmod($path, 0o600);
    }
}
