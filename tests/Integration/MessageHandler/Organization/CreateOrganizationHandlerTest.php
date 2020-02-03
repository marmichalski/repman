<?php

declare(strict_types=1);

namespace Buddy\Repman\Tests\Integration\MessageHandler\Organization;

use Buddy\Repman\Entity\Organization;
use Buddy\Repman\Entity\User;
use Buddy\Repman\Message\Organization\CreateOrganization;
use Buddy\Repman\Tests\Integration\IntegrationTestCase;
use Munus\Control\Option;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class CreateOrganizationHandlerTest extends IntegrationTestCase
{
    public function testSuccess(): void
    {
        $owner = $this->sampleUser();
        $name = 'Acme Inc.';

        $error = $this->createOrganization(
            $id = Uuid::uuid4()->toString(),
            $owner->id()->toString(),
            $name
        );

        $organization = $this->entityManager()
            ->getRepository(Organization::class)
            ->find($id);

        self::assertTrue($error->isEmpty());

        self::assertInstanceOf(Organization::class, $organization);
        self::assertEquals($id, $organization->id()->toString());

        // check associations
        self::assertEquals($owner->getOrganizations()[0]->id(), $organization->id());
        self::assertEquals($owner->id(), $organization->owner()->id());

        // check fields
        self::assertEquals($name, $organization->name());
        self::assertEquals('acme-inc', $organization->alias());
    }

    public function testOwnerDoesNotExist(): void
    {
        self::expectException('Symfony\Component\Messenger\Exception\HandlerFailedException');
        self::expectExceptionMessage('User does not exist');

        $this->createOrganization(
            $id = Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(), // bogus id
            'Failure Inc.'
        );
    }

    private function sampleUser(): User
    {
        $user = (new User(Uuid::uuid4(), 'a@b.com', []))->setPassword('pass');

        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    /**
     * @return Option<string>
     */
    private function createOrganization(string $id, string $ownerId, string $name): Option
    {
        $envelope = $this
            ->container()
            ->get(MessageBusInterface::class)
            ->dispatch(new CreateOrganization($id, $ownerId, $name));

        /** @var HandledStamp */
        $stamp = $envelope->last(HandledStamp::class);
        $error = $stamp->getResult();

        return $error;
    }
}
