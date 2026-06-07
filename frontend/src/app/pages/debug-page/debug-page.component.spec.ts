import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { DebugPageComponent } from './debug-page.component';
import { DebugService } from '../../core/services/debug/debug.service';

class DebugServiceStub {
  getCatalog = jasmine.createSpy('getCatalog').and.resolveTo({
    ok: true,
    data: {
      unit_types: [{ slug: 'goblin', name: 'Goblin' }],
      dice_definitions: [{ sides: 6 }],
      region_items: [{ slug: 'hay', name: 'Hay' }],
      owned_units: [{ id: 'u1', name: 'Briarjaw', unit_type_slug: 'frontline_bruiser_t1', level: 3, max_level: 6 }],
    },
  });
  grantCurrency = jasmine.createSpy('grantCurrency').and.resolveTo({ ok: true });
  grantUnit = jasmine.createSpy('grantUnit').and.resolveTo({ ok: true });
  grantDie = jasmine.createSpy('grantDie').and.resolveTo({ ok: true });
  grantRegionItem = jasmine.createSpy('grantRegionItem').and.resolveTo({ ok: true });
  setUnitLevel = jasmine.createSpy('setUnitLevel').and.resolveTo({ ok: true });
  resetAccount = jasmine.createSpy('resetAccount').and.resolveTo({ ok: true });
}

describe('DebugPageComponent', () => {
  it('loads the debug catalog on startup', async () => {
    await TestBed.configureTestingModule({
      imports: [DebugPageComponent],
      providers: [{ provide: DebugService, useClass: DebugServiceStub }, provideRouter([])],
    }).compileComponents();

    const fixture = TestBed.createComponent(DebugPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    const component = fixture.componentInstance;
    expect(component.loading()).toBeFalse();
    expect(component.catalog()?.unit_types.length).toBe(1);
    expect(component.selectedUnitSlug).toBe('goblin');
    expect(component.selectedOwnedUnitId).toBe('u1');
    expect(component.selectedOwnedUnitLevel).toBe(3);
  });
});
