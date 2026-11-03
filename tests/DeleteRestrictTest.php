<?php

namespace App\Tests;

use ApiPlatform\Test\ApiTestCase;
use App\DataFixtures\CityFixtures;
use App\DataFixtures\CountryFixtures;
use App\DataFixtures\CustomerFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Address;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\User;
use App\Tests\Support\FindsFixtureIds;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A Country or a City that still has children is not deleted (409), and nothing is removed with it.
 */
class DeleteRestrictTest extends ApiTestCase
{
    use FindsFixtureIds;

    protected static ?bool $alwaysBootKernel = true;

    private HttpClientInterface $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = $this->createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();
        $this->client->getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            UserFixtures::class,
            CountryFixtures::class,
            CityFixtures::class,
            CustomerFixtures::class,
        ]);
    }

    public function testDeleteCityWithAddressesIsRefused(): void
    {
        // Berlin has two addresses (Anna Schmidt, Bernd Schmitt).
        $this->client->request('DELETE', '/api/cities/'.$this->idOf(City::class, 'Berlin'), ['headers' => $this->headers()]);

        $this->assertResponseStatusCodeSame(409);
        $this->assertJsonContains(['detail' => 'This resource still has related resources and cannot be deleted.']);
        $this->assertSame(2, $this->rows(City::class));
        $this->assertSame(4, $this->rows(Address::class));
    }

    public function testDeleteCountryWithCitiesIsRefused(): void
    {
        // Germany has Berlin.
        $this->client->request('DELETE', '/api/countries/'.$this->idOf(Country::class, 'Germany'), ['headers' => $this->headers()]);

        $this->assertResponseStatusCodeSame(409);
        $this->assertSame(2, $this->rows(Country::class));
        $this->assertSame(2, $this->rows(City::class));
    }

    /**
     * @param class-string $class
     */
    private function rows(string $class): int
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository($class)->count([]);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);

        return [
            'x-api-key' => $user->getApiTokens()->first()->getToken(),
            'Accept' => 'application/ld+json',
        ];
    }
}
