<?php
declare(strict_types=1);

namespace DiceGoblins\Domain\Rewards;

final class ResolvedEventRecord
{
  public function __construct(
    public readonly int $id,
    public readonly int $userId,
    public readonly string $status,
    public readonly string $resolvedAt,
    public readonly ?string $appliedAt,
    public readonly FinalizedRewardResult $result,
  ) {
    if ($id <= 0 || $userId <= 0 || !in_array($status, ['finalized', 'applied'], true)) {
      throw new RewardResultException('Resolved event row identity or status is invalid.');
    }
    if (($status === 'finalized') !== ($appliedAt === null)) {
      throw new RewardResultException('Resolved event row lifecycle is incoherent.');
    }
    $resolved = strtotime($resolvedAt . ' UTC');
    $applied = $appliedAt !== null ? strtotime($appliedAt . ' UTC') : false;
    if ($resolved === false || ($appliedAt !== null && ($applied === false || $applied < $resolved))) {
      throw new RewardResultException('Resolved event row timestamps are incoherent.');
    }
  }

  public function isApplied(): bool { return $this->status === 'applied'; }
}
