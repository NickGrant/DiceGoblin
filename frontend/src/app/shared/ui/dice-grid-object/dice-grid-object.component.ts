import { NgTemplateOutlet } from '@angular/common';
import { Component, HostBinding, computed, input } from '@angular/core';
import { DiceAffixRecord, DiceRecord } from '../../../core/models/api.models';
import { resolveDiceArtStyles } from '../dice-art/dice-art';
import { GridObjectComponent } from '../grid-object/grid-object.component';

@Component({
  selector: 'dg-dice-grid-object',
  standalone: true,
  imports: [NgTemplateOutlet],
  templateUrl: './dice-grid-object.component.html',
  styleUrl: './dice-grid-object.component.scss',
})
export class DiceGridObjectComponent extends GridObjectComponent<DiceRecord> {
  readonly displayMode = input<'full' | 'compact'>('full');
  readonly isEquipped = input(false);

  @HostBinding('class.dice-grid-object--compact')
  protected get compactClass(): boolean {
    return this.displayMode() === 'compact';
  }

  readonly artStyles = computed(() => resolveDiceArtStyles(this.object().rarity, this.object().sides, 132));
  readonly title = computed(() => this.resolveTitle());
  readonly rarityLabel = computed(() => this.normalizeLabel(this.object().rarity, 'Common'));
  readonly sizeLabel = computed(() => `d${this.object().sides ?? 6}`);
  readonly affixLabel = computed(() =>
    (this.object().affixes ?? []).map((affix) => this.resolveAffixName(affix)).join(' '),
  );
  readonly affixDescriptions = computed(() =>
    (this.object().affixes ?? [])
      .map((affix) => affix.description?.trim() ?? '')
      .filter((description) => description.length > 0),
  );
  readonly compactDetail = computed(() => this.affixDescriptions()[0] ?? `${this.rarityLabel()} die`);

  private resolveTitle(): string {
    const displayName = this.object().display_name?.trim();
    if (displayName) {
      return displayName;
    }

    const affixLabel = this.affixLabel().trim();
    if (affixLabel) {
      return affixLabel;
    }

    return `${this.rarityLabel()} ${this.sizeLabel()}`;
  }

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

  private normalizeLabel(value: string | null | undefined, fallback: string): string {
    const normalized = value?.trim();
    if (!normalized) {
      return fallback;
    }

    return normalized.charAt(0).toUpperCase() + normalized.slice(1).toLowerCase();
  }
}
