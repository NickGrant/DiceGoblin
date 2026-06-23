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
    affixes: [
      {
        affix_definition_id: 'a1',
        affix_slug: 'guard',
        name: 'Guard',
        description: 'Gain block when this die resolves.',
        value: 2,
      },
      {
        affix_definition_id: 'a2',
        affix_slug: 'bulwark',
        name: 'Bulwark',
        description: 'Increase defense by 1.',
        value: 1,
      },
    ],
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
    const sprite = compiled.querySelector('.sprite') as HTMLImageElement;
    expect(compiled.textContent).toContain('d8');
    expect(compiled.textContent).toContain('Rare');
    expect(compiled.textContent).toContain('Guard Bulwark');
    expect(compiled.textContent).toContain('Gain block when this die resolves.');
    expect(compiled.textContent).toContain('Increase defense by 1.');
    expect(sprite.getAttribute('src')).toContain('/assets/ui/dice/bone_d8.png');
  });
});
