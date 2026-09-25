<?php
declare(strict_types=1);

namespace DiceGoblins\Controllers;

use DiceGoblins\Application\Commands\ProvisionWarbandFixtureCommand;
use DiceGoblins\Application\Commands\AbandonRunCommand;
use DiceGoblins\Application\Commands\ActiveRunConfigurationPolicy;
use DiceGoblins\Application\Commands\ActivateSquadCommand;
use DiceGoblins\Application\Commands\CreateSquadCommand;
use DiceGoblins\Application\Commands\DeleteSquadCommand;
use DiceGoblins\Application\Commands\SquadCommandSupport;
use DiceGoblins\Application\Commands\RenameUnitCommand;
use DiceGoblins\Application\Commands\ReplaceUnitLoadoutCommand;
use DiceGoblins\Application\Commands\RunParticipationValidator;
use DiceGoblins\Application\Commands\StartRunCommand;
use DiceGoblins\Application\Commands\ResolveRunNodeCommand;
use DiceGoblins\Application\Rewards\RewardApplicationService;
use DiceGoblins\Application\RunNodes\CombatNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\BossNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\LootNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\RestNodeResolutionHandler;
use DiceGoblins\Application\RunNodes\ExitNodeResolutionHandler;
use DiceGoblins\Application\Combat\CombatSnapshotAssembler;
use DiceGoblins\Application\Commands\UnitConfigurationSupport;
use DiceGoblins\Application\RegionAvailabilityPolicy;
use DiceGoblins\Application\Commands\UpdateSquadCommand;
use DiceGoblins\Application\Queries\ActiveSquadQuery;
use DiceGoblins\Application\Queries\ActiveRunSummaryQuery;
use DiceGoblins\Application\Queries\BattlePlaybackQuery;
use DiceGoblins\Application\Queries\CurrentRunQuery;
use DiceGoblins\Application\Queries\DiceCollectionQuery;
use DiceGoblins\Application\Queries\GameBootstrapQuery;
use DiceGoblins\Application\Queries\SquadCollectionQuery;
use DiceGoblins\Application\Queries\UnitCollectionQuery;
use DiceGoblins\Application\Queries\UnitDetailQuery;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Application\UnitSummaryAssembler;
use DiceGoblins\Domain\Energy\EnergyCalculator;
use DiceGoblins\Domain\Energy\EnergySpendCalculator;
use DiceGoblins\Domain\Battles\CombatSeedDeriver;
use DiceGoblins\Domain\CombatStats\BaseLevelStatResolver;
use DiceGoblins\Domain\Rewards\RewardFinalizer;
use DiceGoblins\Combat\Vnext\CombatEngine;
use DiceGoblins\Infrastructure\SystemClock;
use DiceGoblins\Infrastructure\CryptoRewardRollSource;
use DiceGoblins\Repositories\PlayerStateRepository;
use DiceGoblins\Repositories\RunPersistenceRepository;
use DiceGoblins\Repositories\RunNodeResolutionRepository;
use DiceGoblins\Repositories\ResolvedEventRepository;
use DiceGoblins\Repositories\BattlePersistenceRepository;
use DiceGoblins\Repositories\IdempotencyRequestRepository;
use DiceGoblins\Repositories\SquadRepository;
use DiceGoblins\Repositories\UserRepository;
use DiceGoblins\Repositories\UserUnlockRepository;
use DiceGoblins\Repositories\WarbandDiceRepository;
use DiceGoblins\Repositories\WarbandFixtureRepository;
use DiceGoblins\Repositories\WarbandUnitRepository;
use DiceGoblins\Services\CsrfService;
use DiceGoblins\Services\AccountCreationService;
use DiceGoblins\Services\PasswordResetService;
use DiceGoblins\Services\SessionService;
use DiceGoblins\RunGeneration\FixedGraphRunGenerator;
use PDO;

final class ControllerServiceFactory
{
  /**
   * Content-independent infrastructure shared by API controllers.
   *
   * @return array{
   *   userRepo: UserRepository,
   *   playerStateRepo: PlayerStateRepository,
   *   csrfService: CsrfService,
   *   sessionService: SessionService,
   *   passwordResetService: PasswordResetService
   * }
   */
  public static function buildCore(PDO $pdo): array
  {
    $userRepo = new UserRepository($pdo);
    $playerStateRepo = new PlayerStateRepository($pdo);
    $csrfService = new CsrfService();
    $passwordResetService = new PasswordResetService($pdo, $userRepo);
    $sessionService = new SessionService($userRepo, $csrfService);

    return [
      'userRepo' => $userRepo,
      'playerStateRepo' => $playerStateRepo,
      'csrfService' => $csrfService,
      'sessionService' => $sessionService,
      'passwordResetService' => $passwordResetService,
    ];
  }

  /** @param array<string,mixed>|null $core @return array<string,mixed> */
  public static function buildBattleRead(PDO $pdo, ?array $core = null): array
  {
    $core ??= self::buildCore($pdo);
    return array_merge($core, [
      'battlePlaybackQuery' => new BattlePlaybackQuery(
        new BattlePersistenceRepository($pdo),
        $core['playerStateRepo'],
      ),
    ]);
  }

  /**
   * Adds the one validated registry shared by content-dependent operations in
   * the current composition graph.
   *
   * @param array<string,mixed>|null $core
   * @return array<string,mixed>
   */
  public static function buildContentAware(PDO $pdo, ?array $core = null, ?ContentRegistry $content = null): array
  {
    $core ??= self::buildCore($pdo);
    $content ??= ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    $unitRepository = new WarbandUnitRepository($pdo);
    $diceRepository = new WarbandDiceRepository($pdo);
    $squadRepository = new SquadRepository($pdo);
    $unitSummaries = new UnitSummaryAssembler($content);
    $activeSquadQuery = new ActiveSquadQuery($squadRepository, $unitSummaries);
    $squadCommandSupport = new SquadCommandSupport($core['playerStateRepo'], $squadRepository, $unitRepository, $unitSummaries);
    $unitDetailQuery = new UnitDetailQuery($unitRepository, $content);
    $unitConfigurationSupport = new UnitConfigurationSupport(
      $core['playerStateRepo'],
      $diceRepository,
      $unitDetailQuery,
      $content,
    );
    $idempotencyRepository = new IdempotencyRequestRepository($pdo);
    $runRepository = new RunPersistenceRepository($pdo);
    $activeRunPolicy = new ActiveRunConfigurationPolicy($runRepository);
    $activeRunSummary = new ActiveRunSummaryQuery($runRepository, $content);
    $unlockRepository = new UserUnlockRepository($pdo);
    $regionAvailability = new RegionAvailabilityPolicy($content);
    $nodeResolutionRepository = new RunNodeResolutionRepository($pdo);
    $rewardApplication = new RewardApplicationService(
      $pdo, $content, new RewardFinalizer(new CryptoRewardRollSource()), new ResolvedEventRepository($pdo),
      $core['playerStateRepo'], $unitRepository, $unlockRepository,
    );
    $combatNodeHandler = new CombatNodeResolutionHandler(
      $nodeResolutionRepository,
      new BattlePersistenceRepository($pdo),
      new CombatSnapshotAssembler($content, $squadRepository, $unitDetailQuery, $diceRepository, new BaseLevelStatResolver()),
      new CombatEngine(),
      new CombatSeedDeriver(),
    );

    return array_merge($core, [
      'contentRegistry' => $content,
      'warbandUnitRepository' => $unitRepository,
      'warbandDiceRepository' => $diceRepository,
      'squadRepository' => $squadRepository,
      'userUnlockRepository' => $unlockRepository,
      'accountCreationService' => new AccountCreationService(
        $pdo,
        $core['userRepo'],
        $core['playerStateRepo'],
        $content->startingEnergy(),
      ),
      'gameBootstrapQuery' => new GameBootstrapQuery(
        $core['userRepo'],
        $core['playerStateRepo'],
        $unlockRepository,
        $regionAvailability,
        $content,
        $core['csrfService'],
        new EnergyCalculator(),
        $activeSquadQuery,
        $activeRunSummary,
      ),
      'unitCollectionQuery' => new UnitCollectionQuery($unitRepository, $content),
      'unitDetailQuery' => $unitDetailQuery,
      'diceCollectionQuery' => new DiceCollectionQuery($diceRepository, $content),
      'squadCollectionQuery' => new SquadCollectionQuery($squadRepository),
      'createSquadCommand' => new CreateSquadCommand($pdo, $core['playerStateRepo'], $squadRepository,
        $idempotencyRepository, $squadCommandSupport),
      'updateSquadCommand' => new UpdateSquadCommand($pdo, $core['playerStateRepo'], $squadRepository, $squadCommandSupport, $activeRunPolicy),
      'activateSquadCommand' => new ActivateSquadCommand($pdo, $core['playerStateRepo'], $squadCommandSupport, $activeRunPolicy),
      'deleteSquadCommand' => new DeleteSquadCommand($pdo, $core['playerStateRepo'], $squadRepository, $squadCommandSupport, $activeRunPolicy),
      'renameUnitCommand' => new RenameUnitCommand($pdo, $core['playerStateRepo'], $unitRepository, $unitConfigurationSupport),
      'replaceUnitLoadoutCommand' => new ReplaceUnitLoadoutCommand($pdo, $core['playerStateRepo'], $unitRepository, $unitConfigurationSupport, $activeRunPolicy),
      'currentRunQuery' => new CurrentRunQuery($runRepository, $core['playerStateRepo'], $content),
      'abandonRunCommand' => new AbandonRunCommand($pdo, $core['playerStateRepo'], $runRepository, $content, new SystemClock()),
      'startRunCommand' => new StartRunCommand(
        $pdo,
        $core['playerStateRepo'],
        $squadRepository,
        $runRepository,
        $idempotencyRepository,
        $unlockRepository,
        $content,
        $regionAvailability,
        new FixedGraphRunGenerator(),
        new RunParticipationValidator($content, $unitConfigurationSupport),
        new EnergySpendCalculator(),
        new SystemClock(),
      ),
      'resolveRunNodeCommand' => new ResolveRunNodeCommand(
        $pdo,
        $core['playerStateRepo'],
        $runRepository,
        $nodeResolutionRepository,
        $idempotencyRepository,
        [
          $combatNodeHandler,
          new BossNodeResolutionHandler($combatNodeHandler, $nodeResolutionRepository, $unitRepository, $unlockRepository, $rewardApplication),
          new LootNodeResolutionHandler($nodeResolutionRepository, $unitRepository, $unlockRepository, $rewardApplication),
          new RestNodeResolutionHandler($nodeResolutionRepository, $unitRepository, $content, new BaseLevelStatResolver()),
          new ExitNodeResolutionHandler($nodeResolutionRepository),
        ],
        new SystemClock(),
      ),
      'provisionWarbandFixtureCommand' => new ProvisionWarbandFixtureCommand(
        $pdo,
        new WarbandFixtureRepository($pdo),
        $content,
      ),
    ]);
  }
}
