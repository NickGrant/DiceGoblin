<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PhpBuiltInServerRoutingTest extends TestCase
{
  public function testConfiguredServerRoutesDottedApiPathsThroughTheFrontController(): void
  {
    $backend = dirname(__DIR__, 2);
    $dockerfile = file_get_contents($backend . '/DockerFile');
    $this->assertIsString($dockerfile);
    $this->assertSame(1, preg_match('/^CMD\s+(\[[^\r\n]+\])\s*$/m', $dockerfile, $match));
    $command = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
    $this->assertSame(['php', '-S', '0.0.0.0:8080', '-t', 'public', 'public/index.php'], $command);

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
    $this->assertNotFalse($socket, $errorMessage);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $this->assertIsString($address);
    $port = (int) substr($address, strrpos($address, ':') + 1);
    $this->assertGreaterThan(0, $port);

    $command[0] = PHP_BINARY;
    $command[2] = '127.0.0.1:' . $port;
    $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, $backend);
    $this->assertIsResource($process);
    fclose($pipes[0]);
    try {
      $status = null;
      for ($attempt = 0; $attempt < 30 && $status === null; $attempt++) {
        $status = $this->requestStatus($port, '/api/v1/dialogues/dialogue.test.example/complete');
        if ($status === null) usleep(50_000);
      }
      $this->assertSame(204, $status, 'A dotted dialogue ID must reach front-controller preflight handling.');
      $this->assertSame(204, $this->requestStatus($port, '/api/v1/runs/1/nodes/2/resolve'));
    } finally {
      proc_terminate($process);
      fclose($pipes[1]);
      fclose($pipes[2]);
      proc_close($process);
    }
  }

  private function requestStatus(int $port, string $path): ?int
  {
    $context = stream_context_create(['http' => [
      'method' => 'OPTIONS', 'timeout' => 0.5, 'ignore_errors' => true,
    ]]);
    @file_get_contents('http://127.0.0.1:' . $port . $path, false, $context);
    $headers = $http_response_header ?? [];
    return preg_match('/^HTTP\/\S+\s+(\d{3})/', $headers[0] ?? '', $match) === 1
      ? (int) $match[1] : null;
  }
}
