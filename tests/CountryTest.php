<?php

namespace App\Tests;

use ApiPlatform\Test\ApiTestCase;
use App\DataFixtures\CountryFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Country;
use App\Entity\User;
use App\Tests\Support\FindsFixtureIds;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CountryTest extends ApiTestCase
{
    use FindsFixtureIds;

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
            UserFixtures::class,
        ]);

        $apiToken = $this->getUserApiToken();
        $response = $this->client->request('GET', '/api/countries', [
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
            '@context' => '/api/contexts/Country',
            '@id' => '/api/countries',
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => 2,
        ]);

        $this->assertCount(2, $response->toArray()['hydra:member']);
    }

    public function testCreateCountrySuccess(): void
    {
        $this->databaseTool->loadFixtures([
            CountryFixtures::class,
            UserFixtures::class,
        ]);
        $apiToken = $this->getUserApiToken();

        $this->client->request('POST', '/api/countries', [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'name' => 'Austria',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $this->assertResponseHeaderSame(
            'content-type',
            'application/ld+json'
        );

        $this->assertJsonContains([
            'name' => 'Austria',
        ]);
    }

    public function testGetCountrySuccess(): void
    {
        $this->databaseTool->loadFixtures([
            CountryFixtures::class,
            UserFixtures::class,
        ]);
        $apiToken = $this->getUserApiToken();
        $response = $this->client->request('GET', '/api/countries/'.$this->idOf(Country::class, 'France'), [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        // dd($response);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/api/contexts/Country',
            '@id' => '/api/countries/'.$this->idOf(Country::class, 'France'),
            'name' => 'France',
        ]);
    }

    private function getUserApiToken(): string
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'test@example.com']);

        return UserFixtures::tokenFor($user->getEmail());
    }

    public function testUpdateCountrySuccess(): void
    {
        $this->databaseTool->loadFixtures([
            CountryFixtures::class,
            UserFixtures::class,
        ]);
        $apiToken = $this->getUserApiToken();
        $countryId = $this->idOf(Country::class, 'Germany');
        $this->client->request('PUT', '/api/countries/'.$countryId, [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'name' => 'Italy',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@id' => '/api/countries/'.$countryId,
            'name' => 'Italy',
        ]);
    }

    public function testCountryCanNotBeDeleted(): void
    {
        $this->databaseTool->loadFixtures([
            CountryFixtures::class,
            UserFixtures::class,
        ]);
        $apiToken = $this->getUserApiToken();
        $id = $this->idOf(Country::class, 'France');

        $this->client->request('DELETE', '/api/countries/'.$id, [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(405);
        $this->entityManager->clear();
        $this->assertNotNull($this->entityManager->getRepository(Country::class)->find($id));
    }

    public function testCreateCountrytFailed(): void
    {
        $this->databaseTool->loadFixtures([
            CountryFixtures::class,
            UserFixtures::class,
        ]);
        $apiToken = $this->getUserApiToken();

        $response = $this->client->request('POST', '/api/countries', [
            'headers' => [
                'x-api-key' => $apiToken,
                'Accept' => 'application/ld+json',
                'Content-Type' => 'application/ld+json',
            ],
            'json' => [
                'name' => 12345,
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'status' => 422,
            'detail' => 'name: This value should be of type string.',
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
