<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\IdempotencyKeyException;
use DiceGoblins\Application\Commands\ReconstructionException;
use DiceGoblins\Application\Commands\ReconstructionIntegrityException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use Throwable;

final class WrongMachineReconstructionController
{
  use RequiresCsrf;

  public function __construct(private readonly ?ContentRegistry $content = null) {}

  /** POST /api/v1/wrong-machine/reconstruct */
  public function reconstruct(): void
  {
    try { $pdo = Db::pdo(); $core = ControllerServiceFactory::buildCore($pdo); }
    catch (Throwable) { $this->error('server_error', 'Unexpected error.', 500); return; }
    try { $userId = $core['sessionService']->requireUserId(); }
    catch (Throwable) { $this->error('unauthorized', 'No active session.', 401); return; }
    if (!$this->requireCsrf($core['csrfService'])) return;
    $body = JsonRequestBody::decode();
    if ($body === null) { $this->error('invalid_reconstruction', 'Reconstruction request is invalid.', 422); return; }
    try {
      $services = ControllerServiceFactory::buildContentAware($pdo, $core, $this->content);
      $result = $services['reconstructKinCommand']->execute($userId, $body,
        is_string($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null) ? $_SERVER['HTTP_IDEMPOTENCY_KEY'] : null);
      Response::json(['ok' => true, 'data' => $result]);
    } catch (IdempotencyKeyException) {
      $this->error('idempotency_key_invalid', 'Idempotency-Key is invalid.', 400);
    } catch (IdempotencyConflictException) {
      $this->error('idempotency_conflict', 'Idempotency-Key conflicts with an earlier request.', 409);
    } catch (ReconstructionException $e) {
      $this->error($e->errorCode, $e->publicMessage, $e->httpStatus);
    } catch (ReconstructionIntegrityException) {
      $this->error('wrong_machine_data_integrity_error', 'Wrong Machine data is unavailable.', 500);
    } catch (Throwable) {
      $this->error('server_error', 'Unexpected error.', 500);
    }
  }

  private function error(string $code, string $message, int $status): void
  { Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status); }
}
