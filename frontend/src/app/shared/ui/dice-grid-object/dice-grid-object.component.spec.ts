import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { DiceGridObjectComponent } from './dice-grid-object.component';

@Component({
  standalone: true,
  imports: [DiceGridObjectComponent],
  template: `
    <dg-dice-grid-object
      [object]="die"
      statusText="Equipped by Fang."
    />
  `,
})
class HostComponent {
  readonly die = {
    id: 'd1',
    display_name: 'Stone Die',
    rarity: 'rare',
    sides: 8,
    sell_value: 12,
    affixes: [{ affix_slug: 'heavy', value: 2 }],
  };
}

describe('DiceGridObjectComponent', () => {
  it('renders die details and status text', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Stone Die');
    expect(compiled.textContent).toContain('d8');
    expect(compiled.textContent).toContain('Equipped by Fang.');
    expect(compiled.textContent).toContain('heavy');
  });
});
