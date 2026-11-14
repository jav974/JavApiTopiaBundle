<?php

namespace Jav\ApiTopiaBundle\Tests\Logger;

use Psr\Log\AbstractLogger;

/**
 * Keeps logs in memory for assertions (unlike BufferingLogger, never writes them to stderr).
 */
class TestLogger extends AbstractLogger
{
    /** @var list<array{0: string, 1: string, 2: array<string, mixed>}> */
    private array $logs = [];

    /**
     * Untyped $message: compatible with psr/log 1 (lowest dependencies) to 3
     */
    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = [(string) $level, (string) $message, $context];
    }

    /**
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    public function cleanLogs(): array
    {
        $logs = $this->logs;
        $this->logs = [];

        return $logs;
    }
}
