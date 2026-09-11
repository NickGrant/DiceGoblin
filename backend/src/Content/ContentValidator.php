<?php
declare(strict_types=1);

namespace DiceGoblins\Content;

final class ContentValidator
{
  private const ID_PATTERN = '/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/';

  /** @param list<array{path: string, document: mixed}> $documents
   *  @return array<string, array<string, mixed>>
   */
  public function validate(array $documents): array
  {
    $definitions = [];
    foreach ($documents as $source) {
      $path = $source['path'];
      $document = $source['document'];
      if (!is_array($document) || array_is_list($document) || array_keys($document) !== ['definitions'] || !is_array($document['definitions']) || !array_is_list($document['definitions'])) {
        throw new ContentValidationException("Content file '{$path}' must be an object containing only a definitions array.");
      }

      foreach ($document['definitions'] as $offset => $definition) {
        $location = "{$path} definitions[{$offset}]";
        if (!is_array($definition) || array_is_list($definition)) {
          throw new ContentValidationException("{$location} must be an object.");
        }
        $id = $definition['id'] ?? null;
        if (!is_string($id) || preg_match(self::ID_PATTERN, $id) !== 1) {
          throw new ContentValidationException("{$location} has an invalid stable id.");
        }
        if (isset($definitions[$id])) {
          throw new ContentValidationException("Duplicate stable id '{$id}'.");
        }
        $this->validateDefinition($definition, $location);
        $definitions[$id] = $definition;
      }
    }

    if ($definitions === []) {
      throw new ContentValidationException('Canonical content must contain at least one definition.');
    }
    $this->validateReferences($definitions);
    ksort($definitions, SORT_STRING);
    return $definitions;
  }

  /** @param array<string, mixed> $definition */
  private function validateDefinition(array $definition, string $location): void
  {
    $type = $definition['type'] ?? null;
    if (!is_string($type)) {
      throw new ContentValidationException("{$location} must have a string type.");
    }

    if ($type === 'gameplay_config') {
      $this->requireExactId($definition, 'config.gameplay', $location);
      $this->requireIntegerInRange($definition, 'starting_energy', 0, 1000000, $location);
      $this->requireStableId($definition, 'starting_region_id', $location);
      return;
    }

    if ($type === 'region') {
      if (!str_starts_with((string)$definition['id'], 'region.')) {
        throw new ContentValidationException("{$location} region id must use the region namespace.");
      }
      $this->requireNonEmptyString($definition, 'display_name', $location);
      $this->requireNonEmptyString($definition, 'description', $location);
      $this->requireNonEmptyString($definition, 'art_key', $location);
      $this->requireIntegerInRange($definition, 'encounter_weight', 1, 1000000, $location);
      return;
    }

    throw new ContentValidationException("{$location} has unsupported type '{$type}'.");
  }

  /** @param array<string, array<string, mixed>> $definitions */
  private function validateReferences(array $definitions): void
  {
    $config = $definitions['config.gameplay'] ?? null;
    if (!is_array($config)) {
      throw new ContentValidationException("Required definition 'config.gameplay' is missing.");
    }
    $regionId = (string)$config['starting_region_id'];
    if (!isset($definitions[$regionId]) || ($definitions[$regionId]['type'] ?? null) !== 'region') {
      throw new ContentValidationException("config.gameplay starting_region_id references missing region '{$regionId}'.");
    }
  }

  /** @param array<string, mixed> $definition */
  private function requireExactId(array $definition, string $id, string $location): void
  {
    if ($definition['id'] !== $id) {
      throw new ContentValidationException("{$location} must use stable id '{$id}'.");
    }
  }

  /** @param array<string, mixed> $definition */
  private function requireStableId(array $definition, string $field, string $location): void
  {
    $value = $definition[$field] ?? null;
    if (!is_string($value) || preg_match(self::ID_PATTERN, $value) !== 1) {
      throw new ContentValidationException("{$location} field '{$field}' must be a stable id.");
    }
  }

  /** @param array<string, mixed> $definition */
  private function requireNonEmptyString(array $definition, string $field, string $location): void
  {
    $value = $definition[$field] ?? null;
    if (!is_string($value) || trim($value) === '') {
      throw new ContentValidationException("{$location} field '{$field}' must be a non-empty string.");
    }
  }

  /** @param array<string, mixed> $definition */
  private function requireIntegerInRange(array $definition, string $field, int $minimum, int $maximum, string $location): void
  {
    $value = $definition[$field] ?? null;
    if (!is_int($value) || $value < $minimum || $value > $maximum) {
      throw new ContentValidationException("{$location} field '{$field}' must be an integer from {$minimum} to {$maximum}.");
    }
  }
}
