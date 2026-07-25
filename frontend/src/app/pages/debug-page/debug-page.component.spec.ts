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
      items: [{ id: 'item-1', slug: 'pig_ear', name: 'Pig Ear', category: 'lineage_material' }],
      region_items: [{ slug: 'hay', name: 'Hay' }],
      owned_units: [{ id: 'u1', name: 'Briarjaw', unit_type_slug: 'frontline_bruiser_t1', level: 3, max_level: 6 }],
    },
  });
  getSeedTables = jasmine.createSpy('getSeedTables').and.callFake((tableName?: string) => Promise.resolve({
    ok: true,
    data: {
      tables: [
        { name: 'unit_types', label: 'Unit Types', row_count: 1 },
        { name: 'enemy_templates', label: 'Enemy Templates', row_count: 1 },
      ],
      selected_table: {
        name: tableName || 'unit_types',
        label: tableName === 'enemy_templates' ? 'Enemy Templates' : 'Unit Types',
        row_count: 1,
        columns: ['id', 'slug', 'ability_set_json'],
        json_columns: ['ability_set_json'],
        rows: [
          {
            id: '1',
            slug: tableName === 'enemy_templates' ? 'kobold_sapper' : 'frontline_bruiser_t1',
            ability_set_json: { version: 1, actives: ['basic_attack_melee'] },
          },
        ],
      },
    },
  }));
  grantCurrency = jasmine.createSpy('grantCurrency').and.resolveTo({ ok: true });
  grantUnit = jasmine.createSpy('grantUnit').and.resolveTo({ ok: true });
  grantDie = jasmine.createSpy('grantDie').and.resolveTo({ ok: true });
  grantItem = jasmine.createSpy('grantItem').and.resolveTo({ ok: true });
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
    expect(component.selectedItem).toBe('pig_ear');
    expect(component.selectedOwnedUnitId).toBe('u1');
    expect(component.selectedOwnedUnitLevel).toBe(3);
    expect(component.seedTables()?.selected_table?.name).toBe('unit_types');
    expect(fixture.nativeElement.textContent).toContain('Seeded Tables');
    expect(fixture.nativeElement.textContent).toContain('frontline_bruiser_t1');
    expect(fixture.nativeElement.textContent).toContain('"ability_set_json"');
    expect(fixture.nativeElement.textContent).toContain('"basic_attack_melee"');
    expect(fixture.nativeElement.querySelector('.debug-seeds__table')).toBeNull();
    expect(fixture.nativeElement.querySelector('.debug-seeds__entry pre')?.textContent).toContain(
      '"slug": "frontline_bruiser_t1"',
    );
  });

  it('loads another seeded table when selected', async () => {
    await TestBed.configureTestingModule({
      imports: [DebugPageComponent],
      providers: [{ provide: DebugService, useClass: DebugServiceStub }, provideRouter([])],
    }).compileComponents();

    const fixture = TestBed.createComponent(DebugPageComponent);
    await fixture.whenStable();

    const debugService = TestBed.inject(DebugService) as unknown as DebugServiceStub;
    await fixture.componentInstance.loadSeedTables('enemy_templates');
    fixture.detectChanges();

    expect(debugService.getSeedTables).toHaveBeenCalledWith('enemy_templates');
    expect(fixture.nativeElement.textContent).toContain('kobold_sapper');
  });
});
