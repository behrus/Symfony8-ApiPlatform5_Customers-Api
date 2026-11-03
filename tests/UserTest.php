<?php

namespace App\Tests;

use ApiPlatform\Test\ApiTestCase;
use App\DataFixtures\UserFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UserTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private HttpClientInterface $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = $this->createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();
        $this->client->getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            UserFixtures::class,
        ]);
    }

    public function testGetCollectionIsForbiddenForNormalUser(): void
    {
        $this->client->request('GET', '/api/users', [
            'headers' => $this->headers('test@example.com'),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetCollectionHidesSensitiveFieldsFromAdmin(): void
    {
        $response = $this->client->request('GET', '/api/users', [
            'headers' => $this->headers('admin@example.com'),
        ]);

        $this->assertResponseIsSuccessful();

        $members = $response->toArray()['hydra:member'];
        $this->assertCount(2, $members);
        foreach ($members as $member) {
            $this->assertArrayHasKey('email', $member);
            $this->assertArrayNotHasKey('password', $member);
            $this->assertArrayNotHasKey('apiTokens', $member);
        }
    }

    public function testCreateUserDoesNotReturnPassword(): void
    {
        $response = $this->client->request('POST', '/api/users', [
            'headers' => $this->headers('admin@example.com'),
            'json' => [
                'email' => 'new@example.com',
                'password' => 'plain-text-secret',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertArrayNotHasKey('password', $response->toArray());
    }

    public function testCreateUserSetsTimestamps(): void
    {
        $this->client->request('POST', '/api/users', [
            'headers' => $this->headers('admin@example.com'),
            'json' => ['email' => 'stamped@example.com'],
        ]);
        $this->assertResponseStatusCodeSame(201);

        $this->entityManager->clear();
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'stamped@example.com']);
        $this->assertNotNull($user->getCreatedAt());
        $this->assertNotNull($user->getUpdatedAt());
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $email): array
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        return [
            'x-api-key' => $user->getApiTokens()->first()->getToken(),
            'Accept' => 'application/ld+json',
            'Content-Type' => 'application/ld+json',
        ];
    }
}
