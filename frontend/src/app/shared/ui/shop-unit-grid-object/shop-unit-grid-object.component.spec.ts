import { TestBed } from '@angular/core/testing';
import { ShopUnitGridObjectComponent } from './shop-unit-grid-object.component';

describe('ShopUnitGridObjectComponent', () => {
  it('renders recruit details', async () => {
    await TestBed.configureTestingModule({
      imports: [ShopUnitGridObjectComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(ShopUnitGridObjectComponent);
    fixture.componentRef.setInput('object', {
      id: 'unit-basic-1',
      name: 'Goblin Bruiser',
      role: 'Frontline',
      cost: 20,
      tierLabel: 'Tier 1',
    });
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Goblin Bruiser');
    expect(compiled.textContent).toContain('Frontline');
    expect(compiled.textContent).toContain('Tier 1');
  });
});
