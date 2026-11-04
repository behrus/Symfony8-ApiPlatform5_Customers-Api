<?php

namespace App\Tests;

use ApiPlatform\Test\ApiTestCase;
use App\DataFixtures\CityFixtures;
use App\DataFixtures\CountryFixtures;
use App\DataFixtures\CustomerFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\City;
use App\Entity\Customer;
use App\Tests\Support\FindsFixtureIds;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
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
    private function headers(): array
    {
        return [
            'x-api-key' => UserFixtures::tokenFor('test@example.com'),
            'Accept' => 'application/ld+json',
        ];
    }
}
