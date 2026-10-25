<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Repositories\UserCodexRepository;
use RuntimeException;

final class CodexQuery
{
  public function __construct(
    private readonly UserCodexRepository $ownership,
    private readonly ContentRegistry $content,
  ) {}

  /** @return array{entries:list<array{id:string,title:string,body:?string,owned:bool}>} */
  public function execute(int $userId): array
  {
    $owned = [];
    foreach ($this->ownership->listIdsForUser($userId) as $id) {
      try { $this->content->codexEntry($id); }
      catch (ContentValidationException $e) { throw new RuntimeException('Persisted Codex identity is unknown.', 0, $e); }
      $owned[$id] = true;
    }
    $entries = [];
    foreach ($this->content->definitionsOfType('codex_entry') as $id => $definition) {
      $isOwned = isset($owned[$id]);
      $entries[] = [
        'id' => $id,
        'title' => $definition['title'],
        'body' => $isOwned ? $definition['body'] : null,
        'owned' => $isOwned,
      ];
    }
    return ['entries' => $entries];
  }
}
