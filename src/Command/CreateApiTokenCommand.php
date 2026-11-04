<?php

namespace App\Command;

use App\Entity\ApiToken;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:api-token:create', description: 'Create an API token for a user and print it once')]
class CreateApiTokenCommand
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email of an existing user')] string $email,
    ): int {
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (null === $user) {
            $io->error(sprintf('No user found with email "%s".', $email));

            return Command::FAILURE;
        }

        $token = bin2hex(random_bytes(60));
        $apiToken = new ApiToken();
        $apiToken->setTokenHash(ApiToken::hash($token));
        $user->addApiToken($apiToken);

        $this->entityManager->persist($apiToken);
        $this->entityManager->flush();

        $io->writeln($token);

        return Command::SUCCESS;
    }
}
