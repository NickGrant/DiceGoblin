<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Commands\DialogueCompletionException;
use DiceGoblins\Application\Commands\DialogueCompletionIntegrityException;
use DiceGoblins\Application\Commands\IdempotencyConflictException;
use DiceGoblins\Application\Commands\IdempotencyKeyException;
use DiceGoblins\Application\Queries\DialogueQuery;
use DiceGoblins\Application\Queries\DialogueUnavailableException;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Controllers\Concerns\RequiresCsrf;
use DiceGoblins\Core\Db;
use DiceGoblins\Core\Response;
use DiceGoblins\Http\JsonRequestBody;
use DiceGoblins\Repositories\UserCodexRepository;
use Throwable;

final class DialogueController
{
  use RequiresCsrf;

  public function __construct(private readonly ?ContentRegistry $content = null) {}

  public function read(?string $dialogueId = null): void
  {
    try { $pdo = Db::pdo(); $core = ControllerServiceFactory::buildCore($pdo); }
    catch (Throwable) { $this->error('server_error', 'Unexpected error.', 500); return; }
    try { $userId = $core['sessionService']->requireUserId(); }
    catch (Throwable) { $this->error('unauthorized', 'No active session.', 401); return; }
    try {
      $content = $this->content ?? ContentRegistry::load(dirname(__DIR__, 2) . '/content');
      $result = (new DialogueQuery(new UserCodexRepository($pdo), $content))->execute($userId, (string)$dialogueId);
      Response::json(['ok' => true, 'data' => $result]);
    } catch (DialogueUnavailableException) {
      $this->error('dialogue_unavailable', 'Dialogue is unavailable.', 404);
    } catch (Throwable) {
      $this->error('dialogue_data_integrity_error', 'Dialogue data is unavailable.', 500);
    }
  }

  public function complete(?string $dialogueId = null): void
  {
    try { $pdo = Db::pdo(); $core = ControllerServiceFactory::buildCore($pdo); }
    catch (Throwable) { $this->error('server_error', 'Unexpected error.', 500); return; }
    try { $userId = $core['sessionService']->requireUserId(); }
    catch (Throwable) { $this->error('unauthorized', 'No active session.', 401); return; }
    if (!$this->requireCsrf($core['csrfService'])) return;
    $body = JsonRequestBody::decode();
    if (!is_array($body) || array_is_list($body) || array_keys($body) !== ['choice_id']
      || !is_string($body['choice_id'])) {
      $this->error('invalid_dialogue_choice', 'Dialogue choice request is invalid.', 422); return;
    }
    try {
      $services = ControllerServiceFactory::buildContentAware($pdo, $core, $this->content);
      $result = $services['completeDialogueCommand']->execute($userId, (string)$dialogueId, $body['choice_id'],
        is_string($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null) ? $_SERVER['HTTP_IDEMPOTENCY_KEY'] : null);
      Response::json(['ok' => true, 'data' => $result]);
    } catch (IdempotencyKeyException) {
      $this->error('idempotency_key_invalid', 'Idempotency-Key is invalid.', 400);
    } catch (IdempotencyConflictException) {
      $this->error('idempotency_conflict', 'Idempotency-Key conflicts with an earlier request.', 409);
    } catch (DialogueCompletionException $e) {
      $this->error($e->errorCode, $e->getMessage(), $e->httpStatus);
    } catch (DialogueCompletionIntegrityException) {
      $this->error('dialogue_data_integrity_error', 'Dialogue data is unavailable.', 500);
    } catch (Throwable) {
      $this->error('server_error', 'Unexpected error.', 500);
    }
  }

  private function error(string $code, string $message, int $status): void
  { Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status); }
}
