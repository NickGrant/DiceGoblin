import { NgTemplateOutlet } from '@angular/common';
import { Component } from '@angular/core';
import { GridObjectComponent } from '../grid-object/grid-object.component';

export interface ShopUnitGridObjectRecord {
  id: string;
  name: string;
  role: string;
  cost: number;
  unitTypeSlug: string;
  tierLabel: string;
  tag?: string;
}

@Component({
  selector: 'dg-shop-unit-grid-object',
  standalone: true,
  imports: [NgTemplateOutlet],
  templateUrl: './shop-unit-grid-object.component.html',
  styleUrl: './shop-unit-grid-object.component.scss',
})
export class ShopUnitGridObjectComponent extends GridObjectComponent<ShopUnitGridObjectRecord> {}
