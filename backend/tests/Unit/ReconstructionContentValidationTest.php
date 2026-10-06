<?php
declare(strict_types=1);

namespace DiceGoblins\Tests\Unit;

use DiceGoblins\Content\ClientContentProjector;
use DiceGoblins\Content\ContentRegistry;
use DiceGoblins\Content\ContentValidationException;
use DiceGoblins\Content\ContentValidator;
use PHPUnit\Framework\TestCase;

final class ReconstructionContentValidationTest extends TestCase
{
  public function testProductionPigAndLizardRecipesUseOneValidatedModel(): void
  {
    $registry = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    $this->assertCount(2, $registry->definitionsOfType('reconstruction_recipe'));
    foreach ([
      ['reconstruct_pig_kin', 'pig', 'pig_ear', 'mudking_crown_fragment'],
      ['reconstruct_lizard_kin', 'lizard_kin', 'kobold_scale', 'chief_engineer_lens'],
    ] as [$key, $kin, $material, $catalyst]) {
      $recipe = $registry->reconstructionRecipe('reconstruction_recipe.' . $key);
      $this->assertSame('kin.' . $kin, $recipe['kin_id']);
      $this->assertSame('unlock.kin.' . $kin, $recipe['kin_unlock_id']);
      $this->assertSame(['unlock.capability.wrong_machine_access'], $recipe['prerequisite_unlock_ids']);
      $this->assertSame('random_unlocked', $recipe['first_restoration']['unit_type_selection']);
      $this->assertSame('chosen_unlocked', $recipe['repeat_reconstruction']['unit_type_selection']);
      $this->assertSame($recipe['first_restoration']['price'], $recipe['repeat_reconstruction']['price']);
      $this->assertSame(['currency_id' => 'raw_chaos', 'amount' => 5], $recipe['first_restoration']['price']);
      $this->assertSame($recipe['first_restoration']['ingredients'], $recipe['repeat_reconstruction']['ingredients']);
      $this->assertSame(['item.' . $material, 'item.' . $catalyst], array_column($recipe['first_restoration']['ingredients'], 'item_id'));
      $this->assertSame([3, 1], array_column($recipe['first_restoration']['ingredients'], 'quantity'));
    }
    $content = (new ClientContentProjector())->project($registry)['content'];
    $this->assertArrayNotHasKey('reconstruction_recipes', $content);
    $this->assertArrayNotHasKey('source_region_id', $content['items']['item.kobold_scale']);
    $this->assertArrayNotHasKey('source_encounter_id', $content['items']['item.chief_engineer_lens']);
  }

  public function testInvalidRecipeReferencesAndRequirementsFail(): void
  {
    $recipe = ContentRegistry::load(dirname(__DIR__, 2) . '/content')
      ->reconstructionRecipe('reconstruction_recipe.reconstruct_pig_kin');
    $cases = [
      [array_replace($recipe, ['kin_id' => 'kin.missing']), 'missing kin'],
      [array_replace($recipe, ['kin_unlock_id' => 'unlock.kin.lizard_kin']), 'must restore its target Kin'],
      [array_replace($recipe, ['prerequisite_unlock_ids' => []]), 'must require Wrong Machine access'],
      [array_replace($recipe, ['prerequisite_unlock_ids' => ['unlock.kin.pig']]), 'self prerequisite'],
      [array_replace_recursive($recipe, ['first_restoration' => ['ingredients' => [
        ['item_id' => 'item.missing', 'quantity' => 1]]]]), 'missing item'],
      [array_replace_recursive($recipe, ['first_restoration' => ['ingredients' => [
        ['item_id' => 'item.spark_tonic', 'quantity' => 1]]]]), 'stackable material'],
      [array_replace_recursive($recipe, ['first_restoration' => ['price' => ['amount' => 0]]]), 'amount'],
    ];
    foreach ($cases as [$invalid, $message]) {
      try {
        (new ContentValidator())->validate($this->documents([$recipe['id'] => $invalid]));
        $this->fail('Expected invalid reconstruction content.');
      } catch (ContentValidationException $e) {
        $this->assertStringContainsString($message, $e->getMessage());
      }
    }
  }

  public function testDuplicateKinRecipeFails(): void
  {
    $duplicate = ContentRegistry::load(dirname(__DIR__, 2) . '/content')
      ->reconstructionRecipe('reconstruction_recipe.reconstruct_pig_kin');
    $duplicate['id'] = 'reconstruction_recipe.pig_duplicate';
    $this->expectException(ContentValidationException::class);
    $this->expectExceptionMessage('duplicates Kin restoration');
    (new ContentValidator())->validate($this->documents([], [$duplicate]));
  }

  public function testCyclicRecipePrerequisitesAndIncompatibleMaterialSourcesFail(): void
  {
    $registry = ContentRegistry::load(dirname(__DIR__, 2) . '/content');
    $pig = $registry->reconstructionRecipe('reconstruction_recipe.reconstruct_pig_kin');
    $lizard = $registry->reconstructionRecipe('reconstruction_recipe.reconstruct_lizard_kin');
    $pig['prerequisite_unlock_ids'][] = 'unlock.kin.lizard_kin';
    $lizard['prerequisite_unlock_ids'][] = 'unlock.kin.pig';
    try {
      (new ContentValidator())->validate($this->documents([$pig['id'] => $pig, $lizard['id'] => $lizard]));
      $this->fail('Expected a recipe prerequisite cycle.');
    } catch (ContentValidationException $e) {
      $this->assertStringContainsString('Reconstruction prerequisite cycle', $e->getMessage());
    }
    $catalyst = $registry->definition('item.chief_engineer_lens');
    $catalyst['source_region_id'] = 'region.the_farm';
    $this->expectException(ContentValidationException::class);
    $this->expectExceptionMessage('source encounter is not in its source region');
    (new ContentValidator())->validate($this->documents([$catalyst['id'] => $catalyst]));
  }

  public function testWrongMachineUnlockCannotBeRetargetedToAnUnrelatedCapability(): void
  {
    $unlock = ContentRegistry::load(dirname(__DIR__, 2) . '/content')
      ->unlock('unlock.capability.wrong_machine_access');
    $unlock['target_id'] = 'capability.die_size_d10';
    $this->expectException(ContentValidationException::class);
    $this->expectExceptionMessage('must target its access capability');
    (new ContentValidator())->validate($this->documents([$unlock['id'] => $unlock]));
  }

  /** @param array<string,array<string,mixed>> $replacements
   *  @param list<array<string,mixed>> $extra
   *  @return list<array{path:string,document:mixed}>
   */
  private function documents(array $replacements = [], array $extra = []): array
  {
    $root = dirname(__DIR__, 2) . '/content'; $documents = [];
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
      if (!$file->isFile() || strtolower($file->getExtension()) !== 'json') continue;
      $document = json_decode((string)file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
      foreach ($document['definitions'] as &$definition) {
        if (isset($replacements[$definition['id']])) $definition = $replacements[$definition['id']];
      }
      unset($definition);
      $documents[] = ['path' => $file->getFilename(), 'document' => $document];
    }
    if ($extra !== []) $documents[] = ['path' => 'test/extra.json', 'document' => ['definitions' => $extra]];
    return $documents;
  }
}
