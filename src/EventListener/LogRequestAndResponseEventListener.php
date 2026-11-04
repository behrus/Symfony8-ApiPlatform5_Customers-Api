<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

class LogRequestAndResponseEventListener
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        $this->logger->info('data', [
            'route' => $request->getMethod().' '.$request->getRequestUri(),
            'status' => $response->getStatusCode(),
            'request body' => $request->request->all(),
            'response' => json_decode($response->getContent(), true),
            'headers' => $this->maskSecrets($request->headers->all()),
        ]);
    }

    /**
     * The log must not contain the API token or other credentials.
     *
     * @param array<string, list<string|null>> $headers
     *
     * @return array<string, list<string|null>>
     */
    private function maskSecrets(array $headers): array
    {
        foreach (['x-api-key', 'authorization', 'cookie'] as $name) {
            if (isset($headers[$name])) {
                $headers[$name] = ['***'];
            }
        }

        return $headers;
    }
}
