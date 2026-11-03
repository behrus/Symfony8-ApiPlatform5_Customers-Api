<?php

namespace App\Tests;

use ApiPlatform\Test\ApiTestCase;
use App\DataFixtures\UserFixtures;
use App\Entity\ApiToken;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CreateApiTokenCommandTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private HttpClientInterface $client;
    private EntityManagerInterface $entityManager;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->client = $this->createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();
        $this->client->getContainer()->get(DatabaseToolCollection::class)->get()->loadFixtures([
            UserFixtures::class,
        ]);

        $command = (new Application(self::$kernel))->find('app:api-token:create');
        $this->commandTester = new CommandTester($command);
    }

    public function testCreatesAndStoresTokenForExistingUser(): void
    {
        $exitCode = $this->commandTester->execute(['email' => 'test@example.com']);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $token = trim($this->commandTester->getDisplay());
        $this->assertSame(120, strlen($token));

        $apiToken = $this->entityManager->getRepository(ApiToken::class)->findOneBy(['token' => $token]);
        $this->assertNotNull($apiToken);
        $this->assertSame('test@example.com', $apiToken->getUser()->getEmail());
    }

    public function testFailsForUnknownEmailAndCreatesNoToken(): void
    {
        $tokensBefore = $this->entityManager->getRepository(ApiToken::class)->count([]);

        $exitCode = $this->commandTester->execute(['email' => 'nobody@example.com']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('No user found', $this->commandTester->getDisplay());
        $this->assertSame($tokensBefore, $this->entityManager->getRepository(ApiToken::class)->count([]));
    }

    public function testCreatedTokenAuthenticatesApiRequests(): void
    {
        $this->commandTester->execute(['email' => 'test@example.com']);
        $token = trim($this->commandTester->getDisplay());

        $this->client->request('GET', '/api/countries', [
            'headers' => ['x-api-key' => $token],
        ]);

        $this->assertResponseIsSuccessful();
    }
}
