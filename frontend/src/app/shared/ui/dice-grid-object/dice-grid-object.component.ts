import { NgTemplateOutlet, TitleCasePipe } from '@angular/common';
import { Component, computed } from '@angular/core';
import { DiceAffixRecord, DiceRecord } from '../../../core/models/api.models';
import { resolveDiceArtStyles } from '../dice-art/dice-art';
import { GridObjectComponent } from '../grid-object/grid-object.component';

@Component({
  selector: 'dg-dice-grid-object',
  standalone: true,
  imports: [NgTemplateOutlet, TitleCasePipe],
  templateUrl: './dice-grid-object.component.html',
  styleUrl: './dice-grid-object.component.scss',
})
export class DiceGridObjectComponent extends GridObjectComponent<DiceRecord> {
  readonly artStyles = computed(() => resolveDiceArtStyles(this.object().rarity, this.object().sides, 132));
  readonly affixLabel = computed(() =>
    (this.object().affixes ?? []).map((affix) => this.resolveAffixName(affix)).join(' '),
  );
  readonly affixDescriptions = computed(() =>
    (this.object().affixes ?? [])
      .map((affix) => affix.description?.trim() ?? '')
      .filter((description) => description.length > 0),
  );

  private resolveAffixName(affix: DiceAffixRecord): string {
    const name = affix.name?.trim();
    if (name) {
      return name;
    }

    const slug = affix.affix_slug?.trim();
    if (!slug) {
      return 'Affix';
    }

    return slug
      .split(/[_-]+/)
      .filter((segment) => segment.length > 0)
      .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
      .join(' ');
  }
}
