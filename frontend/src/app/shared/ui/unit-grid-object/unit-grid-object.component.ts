import { NgTemplateOutlet } from '@angular/common';
import { Component, input } from '@angular/core';
import { UnitRecord } from '../../../core/models/api.models';
import { GridObjectComponent } from '../grid-object/grid-object.component';

@Component({
  selector: 'dg-unit-grid-object',
  standalone: true,
  imports: [NgTemplateOutlet],
  templateUrl: './unit-grid-object.component.html',
  styleUrl: './unit-grid-object.component.scss',
})
export class UnitGridObjectComponent extends GridObjectComponent<UnitRecord> {
  readonly tag = input('Unit Record');
}
