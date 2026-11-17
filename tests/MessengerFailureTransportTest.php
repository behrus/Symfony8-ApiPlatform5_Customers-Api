<?php

namespace App\Tests;

use App\Message\CustomerCreated;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Simulates what the worker does when a handler throws: it dispatches WorkerMessageFailedEvent.
 * The default retry strategy allows 3 retries (framework.messenger.transports.*.retry_strategy.max_retries).
 */
class MessengerFailureTransportTest extends KernelTestCase
{
    private EventDispatcherInterface $eventDispatcher;
    private InMemoryTransport $asyncTransport;
    private InMemoryTransport $failedTransport;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->eventDispatcher = $container->get('event_dispatcher');
        $this->asyncTransport = $container->get('messenger.transport.async');
        $this->failedTransport = $container->get('messenger.transport.failed');
    }

    public function testMessageWithRetriesLeftGoesBackToAsyncTransport(): void
    {
        $this->failHandling(new Envelope(new CustomerCreated(1), [new RedeliveryStamp(0)]));

        $this->assertCount(1, $this->asyncTransport->getSent());
        $this->assertCount(0, $this->failedTransport->getSent());
    }

    public function testMessageWithoutRetriesLeftIsStoredInFailureTransport(): void
    {
        $this->failHandling(new Envelope(new CustomerCreated(1), [new RedeliveryStamp(3)]));

        $this->assertCount(0, $this->asyncTransport->getSent());
        $this->assertCount(1, $this->failedTransport->getSent());

        $envelope = $this->failedTransport->getSent()[0];
        $this->assertInstanceOf(CustomerCreated::class, $envelope->getMessage());
        $this->assertSame('async', $envelope->last(SentToFailureTransportStamp::class)->getOriginalReceiverName());
    }

    private function failHandling(Envelope $envelope): void
    {
        $this->eventDispatcher->dispatch(
            new WorkerMessageFailedEvent($envelope, 'async', new \RuntimeException('Handler failed.'))
        );
    }
}
