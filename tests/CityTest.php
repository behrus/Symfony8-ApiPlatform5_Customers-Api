<?php

namespace App\Tests;

use ApiPlatform\Test\ApiTestCase;
use App\DataFixtures\CityFixtures;
use App\DataFixtures\CountryFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CityTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private HttpClientInterface $client;
    private EntityManagerInterface $entityManager;

    private AbstractDatabaseTool $databaseTool;

    protected function setUp(): void
    {
        $this->client = $this->createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();
        $this->databaseTool = $this->client->getContainer()->get(DatabaseToolCollection::class)->get();
    }

    public function testGetCollection(): void
    {
        $this->databaseTool->loadFixtures([
            UserFixtures::class,
            CountryFixtures::class,
            CityFixtures::class,
        ]);

        $apiToken = $this->getUserApiToken();
        $response = $this->client->request('GET', '/api/cities', [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $this->assertResponseHeaderSame(
            'content-type',
            'application/ld+json'
        );

        $this->assertJsonContains([
            '@context' => '/api/contexts/City',
            '@id' => '/api/cities',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $this->assertCount(1, $response->toArray()['hydra:member']);
    }

    public function testCreateCitySuccess(): void
    {
        $this->databaseTool->loadFixtures([
            UserFixtures::class,
            CountryFixtures::class,
            CityFixtures::class,
        ]);
        $apiToken = $this->getUserApiToken();

        $this->client->request('POST', '/api/cities', [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'name' => 'Hamburg',
                'country' => '/api/countries/1',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $this->assertResponseHeaderSame(
            'content-type',
            'application/ld+json'
        );

        $this->assertJsonContains([
            'name' => 'Hamburg',
        ]);
    }

    public function testGetCitySuccess(): void
    {
        $this->databaseTool->loadFixtures([
            UserFixtures::class,
            CountryFixtures::class,
            CityFixtures::class,
        ]);
        $apiToken = $this->getUserApiToken();
        $response = $this->client->request('GET', '/api/cities/1', [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        // dd($response);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/api/contexts/City',
            '@id' => '/api/cities/1',
            'name' => 'Berlin',
            'country' => [
                '@id' => '/api/countries/1',
                '@type' => 'Country',
            ],
        ]);
    }

    private function getUserApiToken(): string
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'test@example.com']);

        return $user->getApiTokens()->first()->getToken();
    }

    public function testUpdateCitySuccess(): void
    {
        $this->databaseTool->loadFixtures([
            UserFixtures::class,
            CountryFixtures::class,
            CityFixtures::class,
        ]);
        $apiToken = $this->getUserApiToken();
        $this->client->request('PUT', '/api/cities/1', [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'name' => 'Bochum',
                'country' => '/api/countries/1',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => '/api/cities/1',
            'name' => 'Bochum',
            'country' => [
                '@id' => '/api/countries/1',
                '@type' => 'Country',
            ],
        ]);
    }

    public function testDeleteCitySuccess(): void
    {
        $this->databaseTool->loadFixtures([
            UserFixtures::class,
            CountryFixtures::class,
            CityFixtures::class,
        ]);
        $apiToken = $this->getUserApiToken();

        $response = $this->client->request('DELETE', '/api/cities/1', [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testCreateCitytFailed(): void
    {
        $this->databaseTool->loadFixtures([
            UserFixtures::class,
            CountryFixtures::class,
            CityFixtures::class,
        ]);
        $apiToken = $this->getUserApiToken();

        $this->client->request('POST', '/api/cities', [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'name' => 12345,
                'country' => '/api/countries/1',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'detail' => 'name: This value should be of type string.',
        ]);
    }
}
