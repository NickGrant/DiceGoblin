<?php
declare(strict_types=1);

namespace DiceGoblins\Application\Queries;

use DateTimeImmutable;
use DateTimeZone;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Domain\Energy\EnergyCalculator;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Services\CsrfService;

final class GameBootstrapQuery
{
  public function __construct(
    private readonly UserRepository $users,
    private readonly PlayerStateRepository $playerState,
    private readonly UserUnlockRepository $unlocks,
    private readonly ContentRegistry $content,
    private readonly CsrfService $csrf,
    private readonly EnergyCalculator $energyCalculator,
    private readonly ActiveSquadQuery $activeSquad,
    private readonly ActiveRunSummaryQuery $activeRun,
  ) {}

  /** @return array<string,mixed> */
  public function execute(int $userId, DateTimeImmutable $now): array
  {
    $account = $this->users->getGameIdentity($userId);
    if ($account === null) {
      throw new GameBootstrapIntegrityException('Authenticated account is missing.');
    }

    $state = $this->playerState->getPlayerState($userId);
    if ($state === null) {
      throw new GameBootstrapIntegrityException('Required player state is missing.');
    }

    $lastRegenerationAt = new DateTimeImmutable(
      (string)$state['energy_last_regen_at'],
      new DateTimeZone('UTC'),
    );
    $energy = $this->energyCalculator->calculate(
      (int)$state['energy_current'],
      $lastRegenerationAt,
      $this->content->energyNormalMaximum(),
      $this->content->energyRegenerationPerHour(),
      $now,
    );

    return [
      'account' => [
        'id' => $account['id'],
        'display_name' => $account['display_name'],
        'role' => $account['role'],
      ],
      'player' => [
        'teeth' => (int)$state['teeth'],
        'raw_chaos' => (int)$state['raw_chaos'],
        'energy' => $energy->toArray(),
        'player_revision' => (int)$state['player_revision'],
      ],
      'session' => [
        'authenticated' => true,
        'csrf_token' => $this->csrf->getOrCreateToken(),
      ],
      'server_time' => $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
      'content_revision' => $this->content->revision(),
      'progression' => [
        'unlock_ids' => $this->unlocks->listIdsForUser($userId),
      ],
      'active_squad' => $this->activeSquad->execute(
        $userId,
        $state['active_squad_id'] !== null ? (int)$state['active_squad_id'] : null,
      ),
      'active_run' => $this->activeRun->execute($userId),
    ];
  }
}
