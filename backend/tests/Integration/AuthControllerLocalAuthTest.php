<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\AuthController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class AuthControllerLocalAuthTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run local auth integration tests.';
  }

  public function testLocalRegisterCreatesUserAndSession(): void
  {
    $email = 'LocalAuth' . bin2hex(random_bytes(4)) . '@example.test';
    $this->setJsonBody([
      'email' => $email,
      'password' => 'secret-pass',
      'display_name' => 'Local Tester',
    ]);

    $response = $this->invoke(fn() => (new AuthController())->localRegister());

    $this->assertSame(201, $response['status'], json_encode($response['body']));
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertSame(true, $response['body']['data']['authenticated'] ?? null);
    $this->assertSame('Local Tester', $response['body']['data']['user']['display_name'] ?? null);
    $this->assertIsString($response['body']['data']['csrf_token'] ?? null);
    $this->assertNotEmpty($_SESSION['user_id'] ?? null);

    $userId = (int)$_SESSION['user_id'];
    $this->trackUserId($userId);
    $storedHash = (string)$this->scalar('SELECT `password_hash` FROM `users` WHERE `id` = ?', [$userId]);
    $this->assertNotSame('secret-pass', $storedHash);
    $this->assertTrue(password_verify('secret-pass', $storedHash));
  }

  public function testLocalLoginRejectsInvalidPassword(): void
  {
    $email = 'bad-login-' . bin2hex(random_bytes(4)) . '@example.test';
    $userId = $this->insertLocalUser($email, 'right-password');

    $this->setJsonBody([
      'email' => $email,
      'password' => 'wrong-password',
    ]);

    $response = $this->invoke(fn() => (new AuthController())->localLogin());

    $this->assertSame(401, $response['status'], json_encode($response['body']));
    $this->assertSame(false, $response['body']['ok'] ?? null);
    $this->assertSame('invalid_credentials', $response['body']['error']['code'] ?? null);
    $this->assertArrayNotHasKey('user_id', $_SESSION);
  }

  public function testLocalRegisterRejectsDuplicateEmail(): void
  {
    $email = 'duplicate-' . bin2hex(random_bytes(4)) . '@example.test';
    $this->insertLocalUser($email, 'right-password');

    $this->setJsonBody([
      'email' => strtoupper($email),
      'password' => 'secret-pass',
      'display_name' => 'Duplicate User',
    ]);

    $response = $this->invoke(fn() => (new AuthController())->localRegister());

    $this->assertSame(409, $response['status'], json_encode($response['body']));
    $this->assertSame(false, $response['body']['ok'] ?? null);
    $this->assertSame('email_already_registered', $response['body']['error']['code'] ?? null);
  }

  public function testLocalLoginEstablishesExistingUserSession(): void
  {
    $email = 'local-login-' . bin2hex(random_bytes(4)) . '@example.test';
    $userId = $this->insertLocalUser($email, 'right-password');

    $this->setJsonBody([
      'email' => strtoupper($email),
      'password' => 'right-password',
    ]);

    $response = $this->invoke(fn() => (new AuthController())->localLogin());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertSame(true, $response['body']['data']['authenticated'] ?? null);
    $this->assertSame($userId, (int)($_SESSION['user_id'] ?? 0));
  }

  public function testPasswordResetRequestAndConfirmUpdatesPasswordAndSession(): void
  {
    $email = 'reset-' . bin2hex(random_bytes(4)) . '@example.test';
    $userId = $this->insertLocalUser($email, 'old-password');

    $this->setJsonBody([
      'email' => strtoupper($email),
    ]);

    $requestResponse = $this->invoke(fn() => (new AuthController())->requestPasswordReset());

    $this->assertSame(200, $requestResponse['status'], json_encode($requestResponse['body']));
    $this->assertSame(true, $requestResponse['body']['ok'] ?? null);
    $this->assertIsString($requestResponse['body']['data']['reset_token'] ?? null);

    $this->setJsonBody([
      'token' => $requestResponse['body']['data']['reset_token'],
      'password' => 'new-password',
    ]);

    $confirmResponse = $this->invoke(fn() => (new AuthController())->confirmPasswordReset());

    $this->assertSame(200, $confirmResponse['status'], json_encode($confirmResponse['body']));
    $this->assertSame(true, $confirmResponse['body']['ok'] ?? null);
    $this->assertSame(true, $confirmResponse['body']['data']['authenticated'] ?? null);
    $this->assertSame($userId, (int)($_SESSION['user_id'] ?? 0));

    $storedHash = (string)$this->scalar('SELECT `password_hash` FROM `users` WHERE `id` = ?', [$userId]);
    $this->assertFalse(password_verify('old-password', $storedHash));
    $this->assertTrue(password_verify('new-password', $storedHash));
  }

  public function testPasswordResetRequestDoesNotRevealUnknownEmail(): void
  {
    $this->setJsonBody([
      'email' => 'missing-' . bin2hex(random_bytes(4)) . '@example.test',
    ]);

    $response = $this->invoke(fn() => (new AuthController())->requestPasswordReset());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertArrayNotHasKey('reset_token', $response['body']['data'] ?? []);
  }

  public function testPasswordResetConfirmRejectsInvalidToken(): void
  {
    $this->setJsonBody([
      'token' => 'not-a-real-token',
      'password' => 'new-password',
    ]);

    $response = $this->invoke(fn() => (new AuthController())->confirmPasswordReset());

    $this->assertSame(400, $response['status'], json_encode($response['body']));
    $this->assertSame(false, $response['body']['ok'] ?? null);
    $this->assertSame('password_reset_invalid', $response['body']['error']['code'] ?? null);
    $this->assertArrayNotHasKey('user_id', $_SESSION);
  }

  private function insertLocalUser(string $email, string $password): int
  {
    $stmt = $this->pdo?->prepare('
      INSERT INTO `users` (`discord_id`, `local_email`, `password_hash`, `display_name`)
      VALUES (NULL, ?, ?, ?)
    ');
    $stmt?->execute([strtolower($email), password_hash($password, PASSWORD_DEFAULT), 'Local User']);
    $userId = (int)$this->pdo?->lastInsertId();
    $this->trackUserId($userId);
    return $userId;
  }
}
