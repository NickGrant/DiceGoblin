import { NgTemplateOutlet, TitleCasePipe } from '@angular/common';
import { Component, computed, input } from '@angular/core';
import { DiceRecord } from '../../../core/models/api.models';
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
}
