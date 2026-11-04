<?php

namespace App\Tests;

use ApiPlatform\Test\ApiTestCase;
use App\DataFixtures\CityFixtures;
use App\DataFixtures\CountryFixtures;
use App\DataFixtures\CustomerFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Address;
use App\Entity\City;
use App\Entity\Customer;
use App\Tests\Support\FindsFixtureIds;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The API works with the DTO resources CustomerResource and AddressResource, not with the entities.
 */
class CustomerResourceTest extends ApiTestCase
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

    public function testResponseShowsOnlyTheDeclaredFields(): void
    {
        $id = $this->idOf(Customer::class, 'Anna Schmidt');

        $response = $this->client->request('GET', '/api/customers/'.$id, ['headers' => $this->headers()]);

        $this->assertResponseIsSuccessful();
        $keys = array_keys($response->toArray());
        sort($keys);
        $this->assertSame(['@context', '@id', '@type', 'address', 'createdAt', 'email', 'name', 'phone', 'updatedAt'], $keys);
    }

    public function testPutWithUnchangedEmailIsAccepted(): void
    {
        $id = $this->idOf(Customer::class, 'Anna Schmidt');

        $this->client->request('PUT', '/api/customers/'.$id, [
            'headers' => $this->headers() + ['Content-Type' => 'application/ld+json'],
            'json' => ['name' => 'Anna Schmidt-Neu', 'email' => 'anna@example.com', 'phone' => '999'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->entityManager->clear();
        $this->assertSame('Anna Schmidt-Neu', $this->entityManager->getRepository(Customer::class)->find($id)->getName());
    }

    public function testPutWithEmailOfAnotherCustomerIsRejected(): void
    {
        $id = $this->idOf(Customer::class, 'Anna Schmidt');

        $this->client->request('PUT', '/api/customers/'.$id, [
            'headers' => $this->headers() + ['Content-Type' => 'application/ld+json'],
            'json' => ['name' => 'Anna Schmidt', 'email' => 'clara@example.com', 'phone' => '999'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'email']]]);
    }

    public function testPatchChangesOnlyTheGivenField(): void
    {
        $id = $this->idOf(Customer::class, 'Anna Schmidt');

        $this->client->request('PATCH', '/api/customers/'.$id, [
            'headers' => $this->headers() + ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['phone' => '555'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->entityManager->clear();
        $customer = $this->entityManager->getRepository(Customer::class)->find($id);
        $this->assertSame('555', $customer->getPhone());
        $this->assertSame('Anna Schmidt', $customer->getName());
        $this->assertSame('anna@example.com', $customer->getEmail());
        $this->assertCount(1, $customer->getAddress());
    }

    public function testCreateWithBlankNameIsRejected(): void
    {
        $this->client->request('POST', '/api/customers', [
            'headers' => $this->headers() + ['Content-Type' => 'application/ld+json'],
            'json' => ['name' => '', 'email' => 'blank@example.com', 'phone' => '1'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains(['violations' => [['propertyPath' => 'name']]]);
    }

    public function testCreateWithAddressSavesTheAddress(): void
    {
        $cityId = $this->idOf(City::class, 'Berlin');

        $response = $this->client->request('POST', '/api/customers', [
            'headers' => $this->headers() + ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'With Address',
                'email' => 'with-address@example.com',
                'phone' => '123456',
                'address' => [$this->addressData('Hauptstraße', $cityId)],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertCount(1, $data['address']);
        $this->assertSame('Hauptstraße', $data['address'][0]['street']);
        $this->assertSame('/api/customers/'.basename($data['@id']), $data['address'][0]['customer']);
        $this->entityManager->clear();
        $customer = $this->entityManager->getRepository(Customer::class)->findOneBy(['email' => 'with-address@example.com']);
        $this->assertCount(1, $customer->getAddress());
    }

    public function testCreateWithTwoAddressesSavesBoth(): void
    {
        $cityId = $this->idOf(City::class, 'Berlin');

        $this->client->request('POST', '/api/customers', [
            'headers' => $this->headers() + ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Two Addresses',
                'email' => 'two-addresses@example.com',
                'phone' => '123456',
                'address' => [$this->addressData('Erste Straße', $cityId), $this->addressData('Zweite Straße', $cityId)],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->entityManager->clear();
        $customer = $this->entityManager->getRepository(Customer::class)->findOneBy(['email' => 'two-addresses@example.com']);
        $this->assertCount(2, $customer->getAddress());
    }

    public function testCreateWithoutAddressOrWithEmptyListHasNoAddresses(): void
    {
        foreach ([['email' => 'none@example.com'], ['email' => 'empty@example.com', 'address' => []]] as $data) {
            $this->client->request('POST', '/api/customers', [
                'headers' => $this->headers() + ['Content-Type' => 'application/ld+json'],
                'json' => ['name' => 'No Address', 'phone' => '123456'] + $data,
            ]);

            $this->assertResponseStatusCodeSame(201);
            $this->entityManager->clear();
            $customer = $this->entityManager->getRepository(Customer::class)->findOneBy(['email' => $data['email']]);
            $this->assertCount(0, $customer->getAddress());
        }
    }

    public function testPutAndPatchIgnoreAddressesAndKeepTheExistingOnes(): void
    {
        $id = $this->idOf(Customer::class, 'Anna Schmidt');
        $addressId = $this->entityManager->getRepository(Customer::class)->find($id)->getAddress()->first()->getId();
        $other = [$this->addressData('Andere Straße', $this->idOf(City::class, 'Berlin'))];

        $this->client->request('PUT', '/api/customers/'.$id, [
            'headers' => $this->headers() + ['Content-Type' => 'application/ld+json'],
            'json' => ['name' => 'Anna Schmidt', 'email' => 'anna@example.com', 'phone' => '999', 'address' => $other],
        ]);
        $this->assertResponseStatusCodeSame(200);

        $this->client->request('PATCH', '/api/customers/'.$id, [
            'headers' => $this->headers() + ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['phone' => '555', 'address' => []],
        ]);
        $this->assertResponseStatusCodeSame(200);

        $this->entityManager->clear();
        $addresses = $this->entityManager->getRepository(Customer::class)->find($id)->getAddress();
        $this->assertCount(1, $addresses);
        $this->assertSame($addressId, $addresses->first()->getId());
    }

    public function testCreateAddressForExistingCustomer(): void
    {
        $customerId = $this->idOf(Customer::class, 'Dora Müller');
        $cityId = $this->idOf(City::class, 'Berlin');

        $response = $this->client->request('POST', '/api/addresses', [
            'headers' => $this->headers() + ['Content-Type' => 'application/ld+json'],
            'json' => [
                'street' => 'Hauptstraße',
                'streetnumber' => '7',
                'zipcode' => '10115',
                'customer' => '/api/customers/'.$customerId,
                'city' => '/api/cities/'.$cityId,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame('/api/customers/'.$customerId, $response->toArray()['customer']);
        $this->entityManager->clear();
        $this->assertCount(1, $this->entityManager->getRepository(Customer::class)->find($customerId)->getAddress());
    }

    public function testCreateAddressWithoutCustomerIsRejected(): void
    {
        $addresses = $this->entityManager->getRepository(Address::class)->count([]);

        $this->client->request('POST', '/api/addresses', [
            'headers' => $this->headers() + ['Content-Type' => 'application/ld+json'],
            'json' => $this->addressData('Ohne Customer', $this->idOf(City::class, 'Berlin')),
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains(['violations' => [[
            'propertyPath' => 'customer',
            'message' => 'An address without a customer cannot be created',
        ]]]);
        $this->assertSame($addresses, $this->entityManager->getRepository(Address::class)->count([]));
    }

    public function testCreateAddressWithoutCityIsRejected(): void
    {
        $addresses = $this->entityManager->getRepository(Address::class)->count([]);
        $data = $this->addressData('Ohne City', $this->idOf(City::class, 'Berlin'));
        unset($data['city']);

        $this->client->request('POST', '/api/addresses', [
            'headers' => $this->headers() + ['Content-Type' => 'application/ld+json'],
            'json' => $data + ['customer' => '/api/customers/'.$this->idOf(Customer::class, 'Dora Müller')],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains(['violations' => [[
            'propertyPath' => 'city',
            'message' => 'An address without a city cannot be created',
        ]]]);
        $this->assertSame($addresses, $this->entityManager->getRepository(Address::class)->count([]));
    }

    #[DataProvider('invalidZipcodes')]
    public function testCreateAddressWithInvalidZipcodeIsRejected(string $zipcode): void
    {
        $this->client->request('POST', '/api/addresses', [
            'headers' => $this->headers() + ['Content-Type' => 'application/ld+json'],
            'json' => ['zipcode' => $zipcode, 'customer' => '/api/customers/'.$this->idOf(Customer::class, 'Dora Müller')]
                + $this->addressData('Hauptstraße', $this->idOf(City::class, 'Berlin')),
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains(['violations' => [[
            'propertyPath' => 'zipcode',
            'message' => 'Postal code must have exactly 5 digits',
        ]]]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidZipcodes(): array
    {
        return [
            'too short' => ['1234'],
            'too long' => ['123456'],
            'letters' => ['abcde'],
            'with a space' => ['10 15'],
        ];
    }

    public function testAddressListShowsTheCustomerAsLink(): void
    {
        $response = $this->client->request('GET', '/api/addresses', ['headers' => $this->headers()]);

        $this->assertResponseIsSuccessful();
        foreach ($response->toArray()['hydra:member'] as $address) {
            $this->assertIsString($address['customer']);
            $this->assertIsArray($address['city']);
        }
    }

    /**
     * @return array<string, string>
     */
    private function addressData(string $street, int $cityId): array
    {
        return [
            'street' => $street,
            'streetnumber' => '1',
            'zipcode' => '10115',
            'city' => '/api/cities/'.$cityId,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'x-api-key' => UserFixtures::tokenFor('test@example.com'),
            'Accept' => 'application/ld+json',
        ];
    }
}
