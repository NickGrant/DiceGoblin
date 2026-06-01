import { NgTemplateOutlet } from '@angular/common';
import { Component } from '@angular/core';
import { GridObjectComponent } from '../grid-object/grid-object.component';

export interface ShopDiceGridObjectRecord {
  id: string;
  label: string;
  rarity: string;
  sides: number;
  cost: number;
  detailLines: string[];
  tag?: string;
}

@Component({
  selector: 'dg-shop-dice-grid-object',
  standalone: true,
  imports: [NgTemplateOutlet],
  templateUrl: './shop-dice-grid-object.component.html',
  styleUrl: './shop-dice-grid-object.component.scss',
})
export class ShopDiceGridObjectComponent extends GridObjectComponent<ShopDiceGridObjectRecord> {}
