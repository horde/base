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

namespace Horde\Horde\Test\Unit\Service;

use DateTimeImmutable;
use Horde\Db\Adapter\Pdo\Sqlite;
use Horde\Horde\Service\SqlOAuthAuthorizationCodeRepository;
use Horde\OAuth\Server\Entity\AuthorizationCode;
use Horde\OAuth\Server\Repository\AuthorizationCodeRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlOAuthAuthorizationCodeRepository::class)]
final class SqlOAuthAuthorizationCodeRepositoryTest extends TestCase
{
    private Sqlite $db;
    private SqlOAuthAuthorizationCodeRepository $repository;

    protected function setUp(): void
    {
        $this->db = new Sqlite(['dbname' => ':memory:']);
        $this->db->execute('
            CREATE TABLE horde_oauth_authorization_codes (
                code VARCHAR(255) NOT NULL PRIMARY KEY,
                client_id VARCHAR(255) NOT NULL,
                identity_id VARCHAR(255) NOT NULL,
                redirect_uri VARCHAR(1024) NOT NULL,
                scope TEXT NOT NULL,
                code_challenge VARCHAR(255),
                code_challenge_method VARCHAR(10),
                nonce VARCHAR(255),
                expires_at INTEGER NOT NULL,
                used INTEGER NOT NULL DEFAULT 0
            )
        ');
        $this->repository = new SqlOAuthAuthorizationCodeRepository($this->db);
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(AuthorizationCodeRepository::class, $this->repository);
    }

    public function testPersistAndFindByCode(): void
    {
        $code = new AuthorizationCode(
            code: 'code-123',
            clientId: 'app-1',
            identityId: 'id-1',
            redirectUri: 'https://app.example.com/callback',
            scope: 'openid email',
            codeChallenge: 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            codeChallengeMethod: 'S256',
            nonce: 'nonce-abc',
            expiresAt: new DateTimeImmutable('@2000000'),
        );
        $this->repository->persist($code);

        $loaded = $this->repository->findByCode('code-123');

        self::assertNotNull($loaded);
        self::assertSame('code-123', $loaded->code);
        self::assertSame('app-1', $loaded->clientId);
        self::assertSame('id-1', $loaded->identityId);
        self::assertSame('https://app.example.com/callback', $loaded->redirectUri);
        self::assertSame('openid email', $loaded->scope);
        self::assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', $loaded->codeChallenge);
        self::assertSame('S256', $loaded->codeChallengeMethod);
        self::assertSame('nonce-abc', $loaded->nonce);
        self::assertSame(2000000, $loaded->expiresAt->getTimestamp());
        self::assertFalse($loaded->used);
    }

    public function testFindByCodeReturnsNullForMissing(): void
    {
        self::assertNull($this->repository->findByCode('nonexistent'));
    }

    public function testMarkUsedAndIsUsed(): void
    {
        $this->persistCode('code-use');

        self::assertFalse($this->repository->isUsed('code-use'));

        $this->repository->markUsed('code-use');

        self::assertTrue($this->repository->isUsed('code-use'));

        $loaded = $this->repository->findByCode('code-use');
        self::assertTrue($loaded->used);
    }

    public function testNullPkceFields(): void
    {
        $code = new AuthorizationCode(
            code: 'code-nopkce',
            clientId: 'app-1',
            identityId: 'id-1',
            redirectUri: 'https://app.example.com/callback',
            scope: 'openid',
            codeChallenge: null,
            codeChallengeMethod: null,
            nonce: null,
            expiresAt: new DateTimeImmutable('@2000000'),
        );
        $this->repository->persist($code);

        $loaded = $this->repository->findByCode('code-nopkce');

        self::assertNull($loaded->codeChallenge);
        self::assertNull($loaded->codeChallengeMethod);
        self::assertNull($loaded->nonce);
    }

    private function persistCode(string $codeValue): void
    {
        $this->repository->persist(new AuthorizationCode(
            code: $codeValue,
            clientId: 'app-1',
            identityId: 'id-1',
            redirectUri: 'https://app.example.com/callback',
            scope: 'openid',
            codeChallenge: null,
            codeChallengeMethod: null,
            nonce: null,
            expiresAt: new DateTimeImmutable('@2000000'),
        ));
    }
}
