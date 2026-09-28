<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Commands\AcademyUpgradeException;
use DiceGoblins\Application\Commands\AcademyUpgradeIntegrityException;
use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\IdempotencyKeyException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use Throwable;

final class AcademyUpgradeController
{
  use RequiresCsrf;

  public function __construct(private readonly ?ContentRegistry $content = null) {}

  public function upgrade(): void
  {
    try { $pdo = Db::pdo(); $core = ControllerServiceFactory::buildCore($pdo); }
    catch (Throwable) { $this->error('server_error', 'Unexpected error.', 500); return; }
    try { $userId = $core['sessionService']->requireUserId(); }
    catch (Throwable) { $this->error('unauthorized', 'No active session.', 401); return; }
    if (!$this->requireCsrf($core['csrfService'])) return;
    $body = JsonRequestBody::decode();
    if ($body === null) { $this->error('invalid_academy_upgrade', 'Academy upgrade request is invalid.', 422); return; }
    try {
      $services = ControllerServiceFactory::buildContentAware($pdo, $core, $this->content);
      $result = $services['upgradeAcademyCommand']->execute($userId, $body,
        is_string($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null) ? $_SERVER['HTTP_IDEMPOTENCY_KEY'] : null);
      Response::json(['ok' => true, 'data' => $result]);
    } catch (IdempotencyKeyException) {
      $this->error('idempotency_key_invalid', 'Idempotency-Key is invalid.', 400);
    } catch (IdempotencyConflictException) {
      $this->error('idempotency_conflict', 'Idempotency-Key conflicts with an earlier request.', 409);
    } catch (AcademyUpgradeException $e) {
      $this->error($e->errorCode, $e->publicMessage, $e->httpStatus);
    } catch (AcademyUpgradeIntegrityException) {
      $this->error('academy_data_integrity_error', 'Academy data is unavailable.', 500);
    } catch (Throwable) {
      $this->error('server_error', 'Unexpected error.', 500);
    }
  }

  private function error(string $code, string $message, int $status): void
  { Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status); }
}
