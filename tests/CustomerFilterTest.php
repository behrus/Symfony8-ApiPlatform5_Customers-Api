<?php

namespace App\Tests;

use ApiPlatform\Test\ApiTestCase;
use App\DataFixtures\CityFixtures;
use App\DataFixtures\CountryFixtures;
use App\DataFixtures\CustomerFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Pins the search and order filters of /api/customers before SearchFilter is replaced (API Platform 4).
 */
class CustomerFilterTest extends ApiTestCase
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

        $this->databaseTool->loadFixtures([
            UserFixtures::class,
            CountryFixtures::class,
            CityFixtures::class,
            CustomerFixtures::class,
        ]);
    }

    /**
     * @param list<string> $expectedNames
     */
    #[DataProvider('searchProvider')]
    public function testSearch(string $query, array $expectedNames): void
    {
        $member = $this->getCustomers($query);

        $this->assertEqualsCanonicalizing($expectedNames, $this->names($member));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function searchProvider(): array
    {
        return [
            'name is partial' => ['name=schmi', ['Anna Schmidt', 'Bernd Schmitt']],
            'name ignores case' => ['name=SCHMI', ['Anna Schmidt', 'Bernd Schmitt']],
            'email is partial and ignores case' => ['email=EXAMPLE.ORG', ['Bernd Schmitt']],
            'city is exact' => ['address.city.name=Berlin', ['Anna Schmidt', 'Bernd Schmitt']],
            'city ignores case' => ['address.city.name=berlin', ['Anna Schmidt', 'Bernd Schmitt']],
            'city with two addresses has no duplicate' => ['address.city.name=Hamburg', ['Bernd Schmitt', 'Clara Meier']],
            'city is not partial' => ['address.city.name=Ber', []],
            'filters are combined with AND' => ['name=schmi&address.city.name=Hamburg', ['Bernd Schmitt']],
            'unknown filter is ignored' => ['foo=bar', ['Anna Schmidt', 'Bernd Schmitt', 'Clara Meier', 'Dora Müller']],
        ];
    }

    public function testOrderByNameAsc(): void
    {
        $member = $this->getCustomers('order[name]=asc');

        $this->assertSame(['Anna Schmidt', 'Bernd Schmitt', 'Clara Meier', 'Dora Müller'], $this->names($member));
    }

    public function testOrderByNameDesc(): void
    {
        $member = $this->getCustomers('order[name]=desc');

        $this->assertSame(['Dora Müller', 'Clara Meier', 'Bernd Schmitt', 'Anna Schmidt'], $this->names($member));
    }

    /**
     * Hydra keys change with API Platform 4/5, so they are only read here.
     *
     * @return list<array<string, mixed>>
     */
    private function getCustomers(string $query): array
    {
        $response = $this->client->request('GET', '/api/customers?'.$query, [
            'headers' => [
                'x-api-key' => $this->getUserApiToken(),
                'Accept' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount($data['hydra:totalItems'], $data['hydra:member']);

        return $data['hydra:member'];
    }

    /**
     * @param list<array<string, mixed>> $member
     *
     * @return list<string>
     */
    private function names(array $member): array
    {
        return array_map(static fn (array $customer): string => $customer['name'], $member);
    }

    private function getUserApiToken(): string
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'test@example.com']);

        return UserFixtures::tokenFor($user->getEmail());
    }
}
