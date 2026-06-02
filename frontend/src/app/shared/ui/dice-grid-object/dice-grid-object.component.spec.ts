import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { DiceGridObjectComponent } from './dice-grid-object.component';

@Component({
  standalone: true,
  imports: [DiceGridObjectComponent],
  template: `
    <dg-dice-grid-object
      [object]="die"
    />
  `,
})
class HostComponent {
  readonly die = {
    id: 'd1',
    rarity: 'rare',
    sides: 8,
    affixes: [{ affix_definition_id: 'a1', affix_slug: 'heavy', value: 2 }],
  };
}

describe('DiceGridObjectComponent', () => {
  it('renders die details and art', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const sprite = compiled.querySelector('.sprite') as HTMLElement;
    expect(compiled.textContent).toContain('d8');
    expect(compiled.textContent).toContain('Rare');
    expect(compiled.textContent).toContain('heavy');
    expect(sprite.style.backgroundImage).toContain('dice_sheet.png');
    expect(sprite.style.backgroundPosition).toContain('-264px');
  });
});
