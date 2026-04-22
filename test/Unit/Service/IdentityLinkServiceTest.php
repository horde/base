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
use Horde\Horde\Service\AuthLink;
use Horde\Horde\Service\AuthLinkRepository;
use Horde\Horde\Service\IdentityLinkService;
use Horde\Identity\Exception\IdentityNotFoundException;
use Horde\Identity\Identity;
use Horde\Identity\IdentityRepository;
use Horde\Identity\IdentityRole;
use Horde\Identity\IdentityStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdentityLinkService::class)]
final class IdentityLinkServiceTest extends TestCase
{
    private AuthLinkRepository&MockObject $linkRepo;
    private IdentityRepository&MockObject $identityRepo;
    private IdentityLinkService $service;

    protected function setUp(): void
    {
        $this->linkRepo = $this->createMock(AuthLinkRepository::class);
        $this->identityRepo = $this->createMock(IdentityRepository::class);
        $this->service = new IdentityLinkService($this->linkRepo, $this->identityRepo);
    }

    public function testResolveByCredentialsFound(): void
    {
        $link = $this->makeLink();
        $identity = $this->makeIdentity('id-1');

        $this->linkRepo->method('resolve')
            ->with('google', 'google-sub-123')
            ->willReturn($link);
        $this->identityRepo->method('get')
            ->with('id-1')
            ->willReturn($identity);

        $result = $this->service->resolveByCredentials('google', 'google-sub-123');

        self::assertNotNull($result);
        self::assertSame('id-1', $result->id);
    }

    public function testResolveByCredentialsNotFound(): void
    {
        $this->linkRepo->method('resolve')
            ->with('google', 'unknown')
            ->willReturn(null);

        self::assertNull($this->service->resolveByCredentials('google', 'unknown'));
    }

    public function testResolveByCredentialsOrphanedLink(): void
    {
        $link = $this->makeLink();

        $this->linkRepo->method('resolve')
            ->willReturn($link);
        $this->identityRepo->method('get')
            ->willThrowException(new IdentityNotFoundException('gone'));

        self::assertNull($this->service->resolveByCredentials('google', 'google-sub-123'));
    }

    public function testResolveByUsernameUsesLocalProvider(): void
    {
        $link = new AuthLink(
            linkId: 1,
            identityId: 'id-1',
            provider: AuthLink::PROVIDER_LOCAL,
            externalId: 'alice',
            externalEmail: null,
            externalDisplayName: null,
            linkedAt: new DateTimeImmutable('@1000000'),
            lastUsedAt: null,
            metadata: null,
        );
        $identity = $this->makeIdentity('id-1');

        $this->linkRepo->method('resolve')
            ->with('local:user', 'alice')
            ->willReturn($link);
        $this->identityRepo->method('get')
            ->with('id-1')
            ->willReturn($identity);

        $result = $this->service->resolveByUsername('alice');

        self::assertNotNull($result);
        self::assertSame('id-1', $result->id);
    }

    public function testLinkToIdentityCreatesLink(): void
    {
        $this->identityRepo->method('exists')
            ->with('id-1')
            ->willReturn(true);

        $savedLink = new AuthLink(
            linkId: 42,
            identityId: 'id-1',
            provider: 'google',
            externalId: 'google-sub-123',
            externalEmail: 'alice@gmail.com',
            externalDisplayName: 'Alice G',
            linkedAt: new DateTimeImmutable(),
            lastUsedAt: null,
            metadata: null,
        );
        $this->linkRepo->method('save')
            ->willReturn($savedLink);

        $result = $this->service->linkToIdentity(
            'id-1',
            'google',
            'google-sub-123',
            'alice@gmail.com',
            'Alice G',
        );

        self::assertSame(42, $result->linkId);
        self::assertSame('id-1', $result->identityId);
    }

    public function testLinkToIdentityThrowsOnMissingIdentity(): void
    {
        $this->identityRepo->method('exists')
            ->with('nonexistent')
            ->willReturn(false);

        $this->expectException(IdentityNotFoundException::class);
        $this->service->linkToIdentity('nonexistent', 'local:user', 'alice');
    }

    public function testCoupleLocalUserUsesLocalProvider(): void
    {
        $this->identityRepo->method('exists')
            ->with('id-1')
            ->willReturn(true);

        $savedLink = new AuthLink(
            linkId: 10,
            identityId: 'id-1',
            provider: AuthLink::PROVIDER_LOCAL,
            externalId: 'alice',
            externalEmail: null,
            externalDisplayName: null,
            linkedAt: new DateTimeImmutable(),
            lastUsedAt: null,
            metadata: null,
        );

        $this->linkRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(function (AuthLink $link): bool {
                return $link->provider === 'local:user'
                    && $link->externalId === 'alice'
                    && $link->linkId === 0;
            }))
            ->willReturn($savedLink);

        $result = $this->service->coupleLocalUser('id-1', 'alice');

        self::assertSame(10, $result->linkId);
        self::assertSame('local:user', $result->provider);
    }

    public function testUnlinkFromIdentityDelegates(): void
    {
        $this->linkRepo->expects(self::once())
            ->method('deleteByProviderAndExternalId')
            ->with('google', 'google-sub-123');

        $this->service->unlinkFromIdentity('google', 'google-sub-123');
    }

    public function testGetLinksForIdentityDelegates(): void
    {
        $links = [$this->makeLink()];
        $this->linkRepo->method('findByIdentity')
            ->with('id-1')
            ->willReturn($links);

        self::assertSame($links, $this->service->getLinksForIdentity('id-1'));
    }

    public function testTouchLastUsedDelegates(): void
    {
        $link = $this->makeLink();
        $this->linkRepo->method('resolve')
            ->with('local:user', 'alice')
            ->willReturn($link);

        $this->linkRepo->expects(self::once())
            ->method('updateLastUsed')
            ->with(
                self::identicalTo($link->linkId),
                self::isInstanceOf(DateTimeImmutable::class),
            );

        $this->service->touchLastUsed('local:user', 'alice');
    }

    public function testTouchLastUsedNoOpWhenNotFound(): void
    {
        $this->linkRepo->method('resolve')
            ->willReturn(null);

        $this->linkRepo->expects(self::never())
            ->method('updateLastUsed');

        $this->service->touchLastUsed('local:user', 'nobody');
    }

    public function testResolveOrCreateReturnsExistingIdentity(): void
    {
        $link = $this->makeLink();
        $identity = $this->makeIdentity('id-1');

        $this->linkRepo->method('resolve')
            ->with('google', 'google-sub-123')
            ->willReturn($link);
        $this->identityRepo->method('get')
            ->with('id-1')
            ->willReturn($identity);

        $this->identityRepo->expects(self::never())->method('save');

        $result = $this->service->resolveOrCreate('google', 'google-sub-123', 'alice@gmail.com');

        self::assertSame('id-1', $result->id);
    }

    public function testResolveOrCreateCreatesNewIdentityAndLink(): void
    {
        $this->linkRepo->method('resolve')
            ->willReturn(null);

        $this->identityRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Identity $identity): bool {
                return $identity->role === IdentityRole::Collaborator
                    && $identity->status === IdentityStatus::Active
                    && $identity->displayName === 'Alice G'
                    && $identity->primaryEmail === 'alice@gmail.com'
                    && $identity->emails === ['alice@gmail.com'];
            }));

        $this->linkRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(function (AuthLink $link): bool {
                return $link->linkId === 0
                    && $link->provider === 'google'
                    && $link->externalId === 'google-sub-123'
                    && $link->externalEmail === 'alice@gmail.com';
            }))
            ->willReturn(new AuthLink(
                linkId: 99,
                identityId: 'generated',
                provider: 'google',
                externalId: 'google-sub-123',
                externalEmail: 'alice@gmail.com',
                externalDisplayName: 'Alice G',
                linkedAt: new DateTimeImmutable(),
                lastUsedAt: null,
                metadata: null,
            ));

        $result = $this->service->resolveOrCreate(
            'google',
            'google-sub-123',
            'alice@gmail.com',
            'Alice G',
        );

        self::assertSame(IdentityRole::Collaborator, $result->role);
        self::assertSame('alice@gmail.com', $result->primaryEmail);
    }

    public function testResolveOrCreateWithCustomRole(): void
    {
        $this->linkRepo->method('resolve')
            ->willReturn(null);

        $this->identityRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Identity $identity): bool {
                return $identity->role === IdentityRole::Principal;
            }));

        $this->linkRepo->method('save')
            ->willReturn(new AuthLink(
                linkId: 100,
                identityId: 'generated',
                provider: 'local:user',
                externalId: 'admin',
                externalEmail: null,
                externalDisplayName: null,
                linkedAt: new DateTimeImmutable(),
                lastUsedAt: null,
                metadata: null,
            ));

        $result = $this->service->resolveOrCreate(
            'local:user',
            'admin',
            role: IdentityRole::Principal,
        );

        self::assertSame(IdentityRole::Principal, $result->role);
    }

    public function testResolveOrCreateWithNullEmail(): void
    {
        $this->linkRepo->method('resolve')
            ->willReturn(null);

        $this->identityRepo->expects(self::once())
            ->method('save')
            ->with(self::callback(function (Identity $identity): bool {
                return $identity->primaryEmail === null
                    && $identity->emails === [];
            }));

        $this->linkRepo->method('save')
            ->willReturn(new AuthLink(
                linkId: 101,
                identityId: 'generated',
                provider: 'saml',
                externalId: 'nameid-abc',
                externalEmail: null,
                externalDisplayName: null,
                linkedAt: new DateTimeImmutable(),
                lastUsedAt: null,
                metadata: null,
            ));

        $result = $this->service->resolveOrCreate('saml', 'nameid-abc');

        self::assertNull($result->primaryEmail);
        self::assertSame([], $result->emails);
    }

    private function makeLink(): AuthLink
    {
        return new AuthLink(
            linkId: 1,
            identityId: 'id-1',
            provider: 'google',
            externalId: 'google-sub-123',
            externalEmail: 'alice@gmail.com',
            externalDisplayName: 'Alice G',
            linkedAt: new DateTimeImmutable('@1000000'),
            lastUsedAt: null,
            metadata: null,
        );
    }

    private function makeIdentity(string $id): Identity
    {
        return new Identity(
            id: $id,
            role: IdentityRole::Principal,
            status: IdentityStatus::Active,
            displayName: 'Alice',
            primaryEmail: 'alice@example.com',
            emails: ['alice@example.com'],
            supersededBy: null,
            createdAt: new DateTimeImmutable('@1000000'),
            updatedAt: new DateTimeImmutable('@1000000'),
        );
    }
}
