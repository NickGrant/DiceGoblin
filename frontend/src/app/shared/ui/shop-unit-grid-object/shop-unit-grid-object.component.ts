import { NgTemplateOutlet, TitleCasePipe } from '@angular/common';
import { Component } from '@angular/core';
import { GridObjectComponent } from '../grid-object/grid-object.component';

export interface ShopUnitGridObjectRecord {
  id: string;
  name: string;
  role: string;
  cost: number;
  tierLabel: string;
}

@Component({
  selector: 'dg-shop-unit-grid-object',
  standalone: true,
  imports: [NgTemplateOutlet, TitleCasePipe],
  templateUrl: './shop-unit-grid-object.component.html',
  styleUrl: './shop-unit-grid-object.component.scss',
})
export class ShopUnitGridObjectComponent extends GridObjectComponent<ShopUnitGridObjectRecord> {}
