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

use Horde\Core\Service\Exception\OAuthTokenNotFoundException;
use Horde\Core\Service\OAuthTokenRepository;
use Horde\Db\Adapter;
use Horde\Horde\Service\SqlOAuthTokenRepository;
use Horde\OAuth\Client\TokenSet;
use Horde\Secret\SecretManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlOAuthTokenRepository::class)]
final class SqlOAuthTokenRepositoryTest extends TestCase
{
    private Adapter&MockObject $db;
    private SecretManager $secret;
    private SqlOAuthTokenRepository $repository;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Adapter::class);
        $this->secret = SecretManager::create('test-key-for-unit-tests');
        $this->repository = new SqlOAuthTokenRepository($this->db, $this->secret);
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(OAuthTokenRepository::class, $this->repository);
    }

    public function testLoadReturnsTokenSet(): void
    {
        $tokenArray = [
            'access_token' => 'access-123',
            'token_type' => 'Bearer',
            'refresh_token' => 'refresh-456',
            'expires_in' => 3600,
            'scope' => 'email profile',
        ];
        $json = json_encode($tokenArray, JSON_THROW_ON_ERROR);
        $encrypted = $this->secret->encrypt($json);

        $this->db->method('selectOne')
            ->with(
                self::stringContains('SELECT token_data'),
                ['user1', 'google']
            )
            ->willReturn(['token_data' => $encrypted->toBase64()]);

        $result = $this->repository->load('user1', 'google');

        self::assertSame('access-123', $result->accessToken);
        self::assertSame('Bearer', $result->tokenType);
        self::assertSame('refresh-456', $result->refreshToken);
    }

    public function testLoadThrowsWhenNoRow(): void
    {
        $this->db->method('selectOne')
            ->willReturn([]);

        $this->expectException(OAuthTokenNotFoundException::class);
        $this->repository->load('user1', 'google');
    }

    public function testSaveInsertsNewRow(): void
    {
        $tokens = new TokenSet('access-123', 'Bearer');

        $this->db->method('update')->willReturn(0);

        $this->db->expects(self::once())
            ->method('insert')
            ->with(
                self::stringContains('INSERT INTO'),
                self::callback(function (array $params): bool {
                    return $params[0] === 'user1'
                        && $params[1] === 'google'
                        && is_string($params[2])
                        && strlen($params[2]) > 0;
                })
            );

        $this->repository->save('user1', 'google', $tokens);
    }

    public function testSaveUpdatesExistingRow(): void
    {
        $tokens = new TokenSet('access-123', 'Bearer');

        $this->db->method('update')->willReturn(1);

        $this->db->expects(self::never())->method('insert');

        $this->repository->save('user1', 'google', $tokens);
    }

    public function testExistsReturnsTrueWhenRowExists(): void
    {
        $this->db->method('selectValue')
            ->with(
                self::stringContains('SELECT COUNT'),
                ['user1', 'google']
            )
            ->willReturn('1');

        self::assertTrue($this->repository->exists('user1', 'google'));
    }

    public function testExistsReturnsFalseWhenNoRow(): void
    {
        $this->db->method('selectValue')
            ->willReturn('0');

        self::assertFalse($this->repository->exists('user1', 'google'));
    }

    public function testDeleteCallsDbDelete(): void
    {
        $this->db->expects(self::once())
            ->method('delete')
            ->with(
                self::stringContains('DELETE FROM'),
                ['user1', 'google']
            );

        $this->repository->delete('user1', 'google');
    }
}
