<?php

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\CountryFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\ApiToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CountryTest extends ApiTestCase
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
            CountryFixtures::class,
            UserFixtures::class
        ]);

        $apiToken = $this->getUserApiToken();
        $response = $this->client->request('GET', '/api/countries', [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json'
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $this->assertResponseHeaderSame(
            'content-type',
            'application/ld+json'
        );

        $this->assertJsonContains([
            '@context'         => '/api/contexts/Country',
            '@id'              => '/api/countries',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 2
        ]);

        $this->assertCount(2, $response->toArray()['hydra:member']);
    }

    public function testCreateCountrySuccess(): void
    {
        $this->databaseTool->loadFixtures([
            CountryFixtures::class,
            UserFixtures::class
        ]);
        $apiToken = $this->getUserApiToken();

        $this->client->request('POST', '/api/countries', [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json'
            ],
            'json'    => [
                'name'         => 'Austria'
            ]
        ]);

        $this->assertResponseStatusCodeSame(201);

        $this->assertResponseHeaderSame(
            'content-type',
            'application/ld+json'
        );

        $this->assertJsonContains([
            'name'        => 'Austria',
        ]);
    }

    public function testGetCountrySuccess(): void
    {
        $this->databaseTool->loadFixtures([
            CountryFixtures::class,
            UserFixtures::class
        ]);
        $apiToken = $this->getUserApiToken();
        $response = $this->client->request('GET', '/api/countries/2', [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json'
            ],
        ]);

        //dd($response);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/api/contexts/Country',
            '@id'         => '/api/countries/2',
            'name' => 'France',
        ]);
    }

    private function getUserApiToken(): string
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'test@example.com']);
        return $user->getApiTokens()->first()->getToken();
    }

    public function testUpdateCountrySuccess(): void
    {
        $this->databaseTool->loadFixtures([
            CountryFixtures::class,
            UserFixtures::class
        ]);
        $apiToken = $this->getUserApiToken();
        $this->client->request('PUT', '/api/countries/1', [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json'
            ],
            'json'    => [
                'name'         => 'Italy'
            ]
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id'         => '/api/countries/1',
            'name' => 'Italy',
        ]);
    }

    public function testDeleteCountrySuccess(): void
    {
        $this->databaseTool->loadFixtures([
            CountryFixtures::class,
            UserFixtures::class
        ]);
        $apiToken = $this->getUserApiToken();

        $response = $this->client->request('DELETE', '/api/countries/2', [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json'
            ],
        ]);

        //dd($response);
        $this->assertResponseIsSuccessful();
    }

    public function testCreateCountrytFailed(): void
    {
        $this->databaseTool->loadFixtures([
            CountryFixtures::class,
            UserFixtures::class
        ]);
        $apiToken = $this->getUserApiToken();

        $response = $this->client->request('POST', '/api/countries', [
             'headers' => [
                 'x-api-key' => $apiToken,
                 'Accept' => 'application/ld+json',
                 'Content-Type' => 'application/ld+json'
             ],
             'json' => [
                 'name' => 12345,
             ]
         ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            '@id'               => '/api/errors/400',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'The type of the "name" attribute must be "string", "integer" given.',
        ]);
    }

    /*        public function testInvalidToken(): void
            {
                $this->client->request('PUT', '/api/products/1', [
                    'headers' => ['x-api-token' => 'fake-token'],
                    'json'    => [
                        'description' => 'An updated description',
                    ]
                ]);

                $this->assertResponseStatusCodeSame(401);
                $this->assertJsonContains([
                    'message'         => 'Invalid credentials.',
                ]);
            }
    */
}
