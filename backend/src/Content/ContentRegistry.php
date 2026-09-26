<?php
declare(strict_types=1);

namespace DiceGoblins\Content;

use JsonException;

final class ContentRegistry
{
  /** @param array<string, array<string, mixed>> $definitions */
  private function __construct(
    private readonly array $definitions,
    private readonly string $revision,
  ) {}

  public static function load(string $contentRoot): self
  {
    if (!is_dir($contentRoot)) {
      throw new ContentValidationException("Content root '{$contentRoot}' does not exist.");
    }
    $root = realpath($contentRoot);
    if ($root === false) {
      throw new ContentValidationException("Content root '{$contentRoot}' cannot be resolved.");
    }

    $paths = [];
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
      if ($file->isFile() && strtolower($file->getExtension()) === 'json') {
        $paths[] = $file->getPathname();
      }
    }
    sort($paths, SORT_STRING);

    $documents = [];
    foreach ($paths as $path) {
      $relativePath = str_replace('\\', '/', substr($path, strlen($root) + 1));
      $json = file_get_contents($path);
      if ($json === false) {
        throw new ContentValidationException("Unable to read content file '{$relativePath}'.");
      }
      try {
        $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
      } catch (JsonException $e) {
        throw new ContentValidationException("Malformed JSON in '{$relativePath}': {$e->getMessage()}", 0, $e);
      }
      $documents[] = ['path' => $relativePath, 'document' => $document];
    }

    $definitions = (new ContentValidator())->validate($documents);
    return new self($definitions, self::revisionFor($definitions));
  }

  public function revision(): string
  {
    return $this->revision;
  }

  /** @return array<string, mixed> */
  public function definition(string $id): array
  {
    if (!isset($this->definitions[$id])) {
      throw new ContentValidationException("Unknown authored content id '{$id}'.");
    }
    return $this->definitions[$id];
  }

  /** @return array<string, array<string, mixed>> */
  public function definitionsOfType(string $type): array
  {
    return array_filter($this->definitions, static fn(array $definition): bool => $definition['type'] === $type);
  }

  public function startingEnergy(): int
  {
    return (int)$this->definition('config.gameplay')['starting_energy'];
  }

  public function energyNormalMaximum(): int
  {
    return (int)$this->definition('config.gameplay')['energy_normal_max'];
  }

  public function energyRegenerationPerHour(): float
  {
    return (float)$this->definition('config.gameplay')['energy_regeneration_per_hour'];
  }

  public function runEnergyCost(): int
  {
    return (int)$this->definition('config.gameplay')['run_energy_cost'];
  }

  public function startingRegionId(): string
  {
    return (string)$this->definition('config.gameplay')['starting_region_id'];
  }

  /** @return array<string, mixed> */
  public function kin(string $id): array
  {
    return $this->definitionOfType($id, 'kin');
  }

  /** @return array<string, mixed> */
  public function unitType(string $id): array
  {
    return $this->definitionOfType($id, 'unit_type');
  }

  /** @return array<string,mixed> */
  public function enemyUnitType(string $id): array
  {
    return $this->definitionOfType($id, 'enemy_unit_type');
  }

  /** @return array<string,mixed> */
  public function encounter(string $id): array
  {
    return $this->definitionOfType($id, 'encounter');
  }

  /** @return array<string, mixed> */
  public function ability(string $id): array
  {
    return $this->definitionOfType($id, 'ability');
  }

  /** @return array<string, mixed> */
  public function diceMaterial(string $id): array
  {
    return $this->definitionOfType($id, 'dice_material');
  }

  /** @return array<string, mixed> */
  public function diceAspect(string $id): array
  {
    return $this->definitionOfType($id, 'dice_aspect');
  }

  /** @return array<string, mixed> */
  public function diceProfile(string $id): array
  {
    return $this->definitionOfType($id, 'dice_profile');
  }

  /** @return array<string, mixed> */
  public function region(string $id): array
  {
    return $this->definitionOfType($id, 'region');
  }

  /** @return array<string, mixed> */
  public function unlock(string $id): array
  {
    return $this->definitionOfType($id, 'unlock');
  }

  /** @return array<string, mixed> */
  public function event(string $id): array
  {
    return $this->definitionOfType($id, 'event');
  }

  /** @return array<string, mixed> */
  public function rewardDefinition(string $id): array
  {
    return $this->definitionOfType($id, 'reward_definition');
  }

  /** @return array<string, mixed> */
  public function item(string $id): array
  {
    return $this->definitionOfType($id, 'item');
  }

  /** @return array<string, mixed> */
  public function runNodeType(string $id): array
  {
    return $this->definitionOfType($id, 'run_node_type');
  }

  /** @return array<string, mixed> */
  public function runGeneration(string $id): array
  {
    return $this->definitionOfType($id, 'run_generation');
  }

  /** @return array<string, mixed> */
  public function runGenerationForRegion(string $regionId): array
  {
    $region = $this->region($regionId);
    if (!isset($region['run_generation_id'])) {
      throw new ContentValidationException("Authored region '{$regionId}' has no run generation definition.");
    }
    return $this->runGeneration((string)$region['run_generation_id']);
  }

  /** @return array<string, mixed> */
  private function definitionOfType(string $id, string $type): array
  {
    $definition = $this->definition($id);
    if (($definition['type'] ?? null) !== $type) {
      throw new ContentValidationException("Authored content id '{$id}' is not type '{$type}'.");
    }
    return $definition;
  }

  /** @param array<string, array<string, mixed>> $definitions */
  private static function revisionFor(array $definitions): string
  {
    $normalized = self::normalize($definitions);
    try {
      return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    } catch (JsonException $e) {
      throw new ContentValidationException('Canonical content could not be revisioned.', 0, $e);
    }
  }

  private static function normalize(mixed $value): mixed
  {
    if (!is_array($value)) return $value;
    if (!array_is_list($value)) ksort($value, SORT_STRING);
    foreach ($value as $key => $child) $value[$key] = self::normalize($child);
    return $value;
  }
}
