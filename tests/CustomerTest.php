<?php

namespace App\Tests;

use ApiPlatform\Test\ApiTestCase;
use App\DataFixtures\CityFixtures;
use App\DataFixtures\CountryFixtures;
use App\DataFixtures\CustomerFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Address;
use App\Entity\Customer;
use App\Entity\User;
use App\Message\CustomerCreated;
use App\Tests\Support\FindsFixtureIds;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CustomerTest extends ApiTestCase
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

    public function testDeleteIsForbiddenForNormalUser(): void
    {
        $id = $this->firstCustomerId();

        $this->client->request('DELETE', '/api/customers/'.$id, [
            'headers' => $this->headers('test@example.com'),
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertNotNull($this->entityManager->getRepository(Customer::class)->find($id));
    }

    public function testAdminDeleteMarksCustomerAsDeleted(): void
    {
        $id = $this->firstCustomerId();

        $this->client->request('DELETE', '/api/customers/'.$id, [
            'headers' => $this->headers('admin@example.com'),
        ]);

        $this->assertResponseStatusCodeSame(204);
        $this->entityManager->clear();
        $customer = $this->entityManager->getRepository(Customer::class)->find($id);
        $this->assertNotNull($customer, 'A customer is never deleted from the database.');
        $this->assertNotNull($customer->getDeletedAt());
    }

    public function testCreateDispatchesCustomerCreated(): void
    {
        $response = $this->client->request('POST', '/api/customers', [
            'headers' => $this->headers('test@example.com') + ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Messenger Customer',
                'email' => 'messenger@example.com',
                'phone' => '123456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $sent = $transport->getSent();
        $this->assertCount(1, $sent);
        $message = $sent[0]->getMessage();
        $this->assertInstanceOf(CustomerCreated::class, $message);
        $this->assertSame((int) basename($response->toArray()['@id']), $message->customerId);
    }

    public function testCreateReturnsTimestamps(): void
    {
        $response = $this->client->request('POST', '/api/customers', [
            'headers' => $this->headers('test@example.com') + ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Timestamp Customer',
                'email' => 'timestamps@example.com',
                'phone' => '123456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertArrayHasKey('updatedAt', $data);
    }

    public function testCreateWithDuplicateEmailIsRejected(): void
    {
        $this->client->request('POST', '/api/customers', [
            'headers' => $this->headers('test@example.com') + ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Duplicate Customer',
                'email' => 'duplicate@example.com',
                'phone' => '123456',
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);

        $this->client->request('POST', '/api/customers', [
            'headers' => $this->headers('test@example.com') + ['Content-Type' => 'application/ld+json'],
            'json' => [
                'name' => 'Duplicate Customer Two',
                'email' => 'duplicate@example.com',
                'phone' => '654321',
            ],
        ]);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testDatabaseRejectsDuplicateEmail(): void
    {
        foreach (['First', 'Second'] as $name) {
            $customer = new Customer();
            $customer->setName($name);
            $customer->setEmail('unique-index@example.com');
            $customer->setPhone('123456');
            $this->entityManager->persist($customer);
        }

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testDeletedCustomerIsNotListed(): void
    {
        $id = $this->firstCustomerId();
        $total = $this->listCustomers()['hydra:totalItems'];

        $this->deleteAsAdmin($id);

        $list = $this->listCustomers();
        $this->assertSame($total - 1, $list['hydra:totalItems']);
        $this->assertNotContains('/api/customers/'.$id, array_column($list['hydra:member'], '@id'));
    }

    public function testDeletedCustomerAnswersNotFound(): void
    {
        $id = $this->firstCustomerId();
        $this->deleteAsAdmin($id);

        $this->client->request('GET', '/api/customers/'.$id, ['headers' => $this->headers('test@example.com')]);
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('PATCH', '/api/customers/'.$id, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'] + $this->headers('test@example.com'),
            'json' => ['name' => 'Changed after delete'],
        ]);
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('DELETE', '/api/customers/'.$id, ['headers' => $this->headers('admin@example.com')]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteKeepsTheAddresses(): void
    {
        $addresses = $this->entityManager->getRepository(Address::class)->count([]);

        $this->deleteAsAdmin($this->firstCustomerId());

        $this->entityManager->clear();
        $this->assertSame($addresses, $this->entityManager->getRepository(Address::class)->count([]));
    }

    public function testEmailOfDeletedCustomerStaysTaken(): void
    {
        $id = $this->firstCustomerId();
        $email = $this->entityManager->getRepository(Customer::class)->find($id)->getEmail();
        $this->deleteAsAdmin($id);

        $this->client->request('POST', '/api/customers', [
            'headers' => $this->headers('test@example.com') + ['Content-Type' => 'application/ld+json'],
            'json' => ['name' => 'Same Email', 'email' => $email, 'phone' => '123456'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testAddressesOfDeletedCustomerAreHidden(): void
    {
        $id = $this->idOf(Customer::class, 'Anna Schmidt');
        $addressId = $this->entityManager->getRepository(Customer::class)->find($id)->getAddress()->first()->getId();
        $this->client->request('GET', '/api/addresses/'.$addressId, ['headers' => $this->headers('test@example.com')]);
        $this->assertResponseStatusCodeSame(200);

        $this->deleteAsAdmin($id);

        $this->client->request('GET', '/api/addresses/'.$addressId, ['headers' => $this->headers('test@example.com')]);
        $this->assertResponseStatusCodeSame(404);
        $list = $this->client->request('GET', '/api/addresses', ['headers' => $this->headers('test@example.com')])->toArray();
        $this->assertNotContains('/api/addresses/'.$addressId, array_column($list['hydra:member'], '@id'));
        $this->assertNotContains('/api/customers/'.$id, array_column($list['hydra:member'], 'customer'));
    }

    private function deleteAsAdmin(int $id): void
    {
        $this->client->request('DELETE', '/api/customers/'.$id, ['headers' => $this->headers('admin@example.com')]);
        $this->assertResponseStatusCodeSame(204);
    }

    /**
     * @return array<string, mixed>
     */
    private function listCustomers(): array
    {
        return $this->client->request('GET', '/api/customers', ['headers' => $this->headers('test@example.com')])->toArray();
    }

    private function firstCustomerId(): int
    {
        return $this->entityManager->getRepository(Customer::class)->findOneBy([])->getId();
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $email): array
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        return [
            'x-api-key' => UserFixtures::tokenFor($user->getEmail()),
            'Accept' => 'application/ld+json',
        ];
    }
}
