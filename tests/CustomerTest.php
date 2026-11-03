<?php

namespace App\Tests;

use ApiPlatform\Test\ApiTestCase;
use App\DataFixtures\CityFixtures;
use App\DataFixtures\CountryFixtures;
use App\DataFixtures\CustomerFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Customer;
use App\Entity\User;
use App\Message\CustomerCreated;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CustomerTest extends ApiTestCase
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

    public function testAdminCanDelete(): void
    {
        $id = $this->firstCustomerId();

        $this->client->request('DELETE', '/api/customers/'.$id, [
            'headers' => $this->headers('admin@example.com'),
        ]);

        $this->assertResponseStatusCodeSame(204);
        $this->entityManager->clear();
        $this->assertNull($this->entityManager->getRepository(Customer::class)->find($id));
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
            'x-api-key' => $user->getApiTokens()->first()->getToken(),
            'Accept' => 'application/ld+json',
        ];
    }
}
