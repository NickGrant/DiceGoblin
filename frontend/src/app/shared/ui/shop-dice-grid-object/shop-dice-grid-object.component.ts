import { NgTemplateOutlet, TitleCasePipe } from '@angular/common';
import { Component, computed } from '@angular/core';
import { resolveDiceArtStyles } from '../dice-art/dice-art';
import { GridObjectComponent } from '../grid-object/grid-object.component';

export interface ShopDiceGridObjectRecord {
  id: string;
  label: string;
  rarity: string;
  sides: number;
  cost: number;
  isPurchased?: boolean;
  detailLines: string[];
}

@Component({
  selector: 'dg-shop-dice-grid-object',
  standalone: true,
  imports: [NgTemplateOutlet, TitleCasePipe],
  templateUrl: './shop-dice-grid-object.component.html',
  styleUrl: './shop-dice-grid-object.component.scss',
})
export class ShopDiceGridObjectComponent extends GridObjectComponent<ShopDiceGridObjectRecord> {
  readonly artStyles = computed(() => resolveDiceArtStyles(this.object().rarity, this.object().sides, 116));
}
