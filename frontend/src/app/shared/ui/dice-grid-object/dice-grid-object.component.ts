import { NgTemplateOutlet } from '@angular/common';
import { Component, input } from '@angular/core';
import { DiceRecord } from '../../../core/models/api.models';
import { GridObjectComponent } from '../grid-object/grid-object.component';

@Component({
  selector: 'dg-dice-grid-object',
  standalone: true,
  imports: [NgTemplateOutlet],
  templateUrl: './dice-grid-object.component.html',
  styleUrl: './dice-grid-object.component.scss',
})
export class DiceGridObjectComponent extends GridObjectComponent<DiceRecord> {
  readonly statusText = input('Unequipped.');
}
