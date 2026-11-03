<?php

namespace App\MessageHandler;

use App\Message\CustomerCreated;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CustomerCreatedHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(CustomerCreated $message): void
    {
        $this->logger->info('Customer created.', ['customerId' => $message->customerId]);
    }
}
