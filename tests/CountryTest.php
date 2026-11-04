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
            'hydra:totalItems' => 1,
        ]);

        $this->assertCount(1, $response->toArray()['hydra:member']);
    }

    public function testGetCountrySuccess(): void
    {
        $this->databaseTool->loadFixtures([
            CountryFixtures::class,
            UserFixtures::class,
        ]);
        $apiToken = $this->getUserApiToken();
        $response = $this->client->request('GET', '/api/countries/'.$this->idOf(Country::class, 'Germany'), [
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
            '@id' => '/api/countries/'.$this->idOf(Country::class, 'Germany'),
            'name' => 'Germany',
        ]);
    }

    public function testCountryIsReadOnly(): void
    {
        $this->databaseTool->loadFixtures([
            CountryFixtures::class,
            UserFixtures::class,
        ]);
        $id = $this->idOf(Country::class, 'Germany');

        foreach ([['POST', '/api/countries'], ['PUT', '/api/countries/'.$id], ['PATCH', '/api/countries/'.$id], ['DELETE', '/api/countries/'.$id]] as [$method, $url]) {
            $this->client->request($method, $url, [
                'headers' => [
                    'x-api-key' => $this->getUserApiToken(),
                    'Accept' => 'application/ld+json',
                    'Content-Type' => 'PATCH' === $method ? 'application/merge-patch+json' : 'application/ld+json',
                ],
                'json' => ['name' => 'Changed'],
            ]);

            $this->assertResponseStatusCodeSame(405, $method);
        }

        $this->entityManager->clear();
        $this->assertSame('Germany', $this->entityManager->getRepository(Country::class)->find($id)->getName());
    }

    private function getUserApiToken(): string
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'test@example.com']);

        return UserFixtures::tokenFor($user->getEmail());
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
