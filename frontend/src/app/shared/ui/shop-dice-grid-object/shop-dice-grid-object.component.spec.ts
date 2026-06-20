import { TestBed } from '@angular/core/testing';
import { ShopDiceGridObjectComponent } from './shop-dice-grid-object.component';

describe('ShopDiceGridObjectComponent', () => {
  it('renders store dice details', async () => {
    await TestBed.configureTestingModule({
      imports: [ShopDiceGridObjectComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(ShopDiceGridObjectComponent);
    fixture.componentRef.setInput('object', {
      id: 'dice-basic-1',
      label: 'Starter d6',
      rarity: 'common',
      sides: 6,
      cost: 15,
      detailLines: ['Basic stock die', 'Purchase for 15 teeth'],
    });
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const sprite = compiled.querySelector('.sprite') as HTMLElement;
    expect(compiled.textContent).toContain('Starter d6');
    expect(compiled.textContent).toContain('Common');
    expect(compiled.textContent).toContain('Basic stock die');
    expect(sprite.style.backgroundImage).toContain('dice_sheet.png');
    expect(sprite.style.backgroundPosition).toContain('-116px -116px');
  });
});
