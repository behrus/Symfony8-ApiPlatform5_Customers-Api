<?php

namespace App\Tests;

use ApiPlatform\Test\ApiTestCase;
use App\DataFixtures\UserFixtures;
use App\Entity\ApiToken;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiTokenTest extends ApiTestCase
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

    public function testStoredValueIsTheHashOfTheDemoToken(): void
    {
        $apiToken = $this->entityManager->getRepository(ApiToken::class)->findOneBy([]);

        $this->assertSame(64, strlen($apiToken->getTokenHash()));
        $this->assertNotSame(UserFixtures::API_TOKEN, $apiToken->getTokenHash());
        $this->assertNotSame(UserFixtures::ADMIN_API_TOKEN, $apiToken->getTokenHash());
    }

    public function testInvalidTokenIsRejected(): void
    {
        $this->client->request('GET', '/api/countries', [
            'headers' => ['x-api-key' => 'not-a-token'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testTheHashItselfIsNotAValidToken(): void
    {
        $hash = ApiToken::hash(UserFixtures::API_TOKEN);

        $this->client->request('GET', '/api/countries', [
            'headers' => ['x-api-key' => $hash],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetCollectionIsForbiddenForNormalUser(): void
    {
        $this->client->request('GET', '/api/api_tokens', [
            'headers' => $this->headers('test@example.com'),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetCollectionNeverReturnsTokenOrHash(): void
    {
        $response = $this->client->request('GET', '/api/api_tokens', [
            'headers' => $this->headers('admin@example.com'),
        ]);

        $this->assertResponseIsSuccessful();
        $members = $response->toArray()['hydra:member'];
        $this->assertNotEmpty($members);
        foreach ($members as $member) {
            $this->assertArrayNotHasKey('token', $member);
            $this->assertArrayNotHasKey('tokenHash', $member);
        }
        $body = $response->getContent();
        $this->assertStringNotContainsString(UserFixtures::API_TOKEN, $body);
        $this->assertStringNotContainsString(ApiToken::hash(UserFixtures::API_TOKEN), $body);
    }

    public function testTokensCannotBeCreatedThroughTheApi(): void
    {
        $this->client->request('POST', '/api/api_tokens', [
            'headers' => $this->headers('admin@example.com') + ['Content-Type' => 'application/ld+json'],
            'json' => ['token' => 'chosen-by-the-client'],
        ]);

        $this->assertResponseStatusCodeSame(405);
    }

    public function testAdminCanDeleteATokenAndItStopsWorking(): void
    {
        $apiToken = $this->entityManager->getRepository(ApiToken::class)->findOneBy(['tokenHash' => ApiToken::hash(UserFixtures::API_TOKEN)]);

        $this->client->request('DELETE', '/api/api_tokens/'.$apiToken->getId(), [
            'headers' => $this->headers('admin@example.com'),
        ]);
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/countries', [
            'headers' => $this->headers('test@example.com'),
        ]);
        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $email): array
    {
        return [
            'x-api-key' => UserFixtures::tokenFor($email),
            'Accept' => 'application/ld+json',
        ];
    }
}
