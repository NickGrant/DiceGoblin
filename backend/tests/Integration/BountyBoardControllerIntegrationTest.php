<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Integration;

use DiceGoblins\Controllers\BountyBoardController;
use DiceGoblins\Tests\Support\IntegrationTestCase;

final class BountyBoardControllerIntegrationTest extends IntegrationTestCase
{
  protected function integrationSkipMessage(): string
  {
    return 'Set TEST_DB_DSN to run bounty board integration tests.';
  }

  public function testBoardSeedsLaunchBounties(): void
  {
    $userId = $this->insertUser('bounty_board', 'Bounty Board User');
    $_SESSION['user_id'] = $userId;

    $response = $this->invoke(fn() => (new BountyBoardController())->board());

    $this->assertSame(200, $response['status'], json_encode($response['body']));
    $data = $this->assertSuccess($response);
    $this->assertSame(3, (int)($data['active_limit'] ?? 0));
    $this->assertSame(0, (int)($data['active_count'] ?? -1));
    $bounties = is_array($data['bounties'] ?? null) ? $data['bounties'] : [];
    $this->assertGreaterThanOrEqual(3, count($bounties));
    $this->assertSame('clear-any-run-once', (string)($bounties[0]['slug'] ?? ''));
    $this->assertSame('available', (string)($bounties[0]['status'] ?? ''));
  }

  public function testAcceptRejectsDuplicateBounty(): void
  {
    $userId = $this->insertUser('bounty_accept', 'Bounty Accept User');
    $this->authenticate($userId);
    $this->setJsonBody(['slug' => 'clear-any-run-once']);

    $first = $this->invoke(fn() => (new BountyBoardController())->accept());
    $this->assertSame(200, $first['status'], json_encode($first['body']));

    $this->authenticate($userId);
    $this->setJsonBody(['slug' => 'clear-any-run-once']);
    $second = $this->invoke(fn() => (new BountyBoardController())->accept());

    $this->assertSame(409, $second['status'], json_encode($second['body']));
    $this->assertSame('bounty_already_accepted', (string)($second['body']['error']['code'] ?? ''));
  }

  public function testSyncCompletesAcceptedRunBountyAndClaimIsIdempotent(): void
  {
    $userId = $this->insertUser('bounty_claim', 'Bounty Claim User');
    $regionId = $this->insertRegion();

    $this->authenticate($userId);
    $this->setJsonBody(['slug' => 'clear-any-run-once']);
    $accept = $this->invoke(fn() => (new BountyBoardController())->accept());
    $this->assertSame(200, $accept['status'], json_encode($accept['body']));

    $acceptedBoard = $this->assertSuccess($accept);
    $accepted = $this->findBounty($acceptedBoard, 'clear-any-run-once');
    $this->assertSame('accepted', (string)($accepted['status'] ?? ''));
    $userBountyId = (string)($accepted['user_bounty_id'] ?? '');
    $this->assertNotSame('', $userBountyId);

    $this->insertRun($userId, $regionId, 91929394, 'completed');

    $this->authenticate($userId);
    $sync = $this->invoke(fn() => (new BountyBoardController())->sync());
    $this->assertSame(200, $sync['status'], json_encode($sync['body']));
    $synced = $this->findBounty($this->assertSuccess($sync), 'clear-any-run-once');
    $this->assertSame('completed', (string)($synced['status'] ?? ''));
    $this->assertSame(1, (int)($synced['progress']['current'] ?? 0));

    $this->authenticate($userId);
    $claim = $this->invoke(fn() => (new BountyBoardController())->claim($userBountyId));
    $this->assertSame(200, $claim['status'], json_encode($claim['body']));
    $claimData = $this->assertSuccess($claim);
    $this->assertSame(true, (bool)($claimData['newly_claimed'] ?? false));
    $this->assertSame(30, (int)($claimData['currency']['soft'] ?? 0));

    $this->authenticate($userId);
    $secondClaim = $this->invoke(fn() => (new BountyBoardController())->claim($userBountyId));
    $this->assertSame(200, $secondClaim['status'], json_encode($secondClaim['body']));
    $secondClaimData = $this->assertSuccess($secondClaim);
    $this->assertSame(false, (bool)($secondClaimData['newly_claimed'] ?? true));
    $this->assertSame(30, (int)($secondClaimData['currency']['soft'] ?? 0));
  }

  /**
   * @param array{status:int,body:array<string,mixed>} $response
   * @return array<string,mixed>
   */
  private function assertSuccess(array $response): array
  {
    $this->assertSame(true, $response['body']['ok'] ?? null);
    $this->assertIsArray($response['body']['data'] ?? null);
    return $response['body']['data'];
  }

  private function authenticate(int $userId): void
  {
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = 'valid_csrf';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'valid_csrf';
  }

  /**
   * @param array<string,mixed> $board
   * @return array<string,mixed>
   */
  private function findBounty(array $board, string $slug): array
  {
    $bounties = is_array($board['bounties'] ?? null) ? $board['bounties'] : [];
    foreach ($bounties as $bounty) {
      if (is_array($bounty) && (string)($bounty['slug'] ?? '') === $slug) {
        return $bounty;
      }
    }

    return [];
  }
}
