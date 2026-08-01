import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { WrongMachinePageComponent } from './wrong-machine-page.component';
import { WrongMachineService } from '../../core/services/wrong-machine/wrong-machine.service';

class WrongMachineServiceStub {
  getReconstructions = jasmine.createSpy('getReconstructions').and.resolveTo({
    ok: true,
    data: {
      feature_unlocked: true,
      reconstructions: [
        {
          lineage_slug: 'pig_kin',
          kin_slug: 'pig_kin',
          name: 'Pig Kin',
          description: 'Reconstruct the first goblin-kin lineage from Farm materials.',
          is_unlocked: false,
          can_reconstruct: true,
          cost: {
            raw_chaos: { quantity_required: 5, quantity_owned: 5, is_met: true },
            items: [
              { item_slug: 'pig_ear', quantity_required: 3, quantity_owned: 3, is_met: true },
              { item_slug: 'mudking_crown_fragment', quantity_required: 1, quantity_owned: 1, is_met: true },
            ],
          },
          missing: [],
          grants: { lineage_slug: 'pig_kin', unit_type_slug: 'frontline_bruiser_t1', unit_count: 1 },
        },
      ],
    },
  });
  reconstruct = jasmine.createSpy('reconstruct').and.resolveTo({
    ok: true,
    data: {
      lineage: {
        lineage_slug: 'pig_kin',
        kin_slug: 'pig_kin',
        name: 'Pig Kin',
        description: '',
        is_default: false,
        is_implicit: false,
        unlocked_at: '2026-07-26T00:00:00Z',
      },
      newly_reconstructed: true,
      spent: { raw_chaos: 5, items: [] },
      granted_unit: { id: 'u-pig', unit_type_slug: 'frontline_bruiser_t1', kin_slug: 'pig_kin' },
      preview: {
        lineage_slug: 'pig_kin',
        kin_slug: 'pig_kin',
        name: 'Pig Kin',
        description: '',
        is_unlocked: true,
        can_reconstruct: false,
        cost: {
          raw_chaos: { quantity_required: 5, quantity_owned: 0, is_met: false },
          items: [],
        },
        missing: [],
        grants: { lineage_slug: 'pig_kin', unit_type_slug: 'frontline_bruiser_t1', unit_count: 1 },
      },
    },
  });
}

describe('WrongMachinePageComponent', () => {
  let wrongMachineService: WrongMachineServiceStub;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [WrongMachinePageComponent],
      providers: [
        provideRouter([]),
        { provide: WrongMachineService, useClass: WrongMachineServiceStub },
      ],
    }).compileComponents();

    wrongMachineService = TestBed.inject(WrongMachineService) as unknown as WrongMachineServiceStub;
  });

  it('renders Pig Kin reconstruction costs and completes reconstruction', async () => {
    const fixture = TestBed.createComponent(WrongMachinePageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Pig Kin');
    expect(compiled.textContent).toContain('5/5');
    expect(compiled.textContent).toContain('Pig Ear');
    expect(compiled.textContent).toContain('All reconstruction requirements are ready.');
    expect(compiled.textContent).toContain('1 Pig Kin unit');
    expect(compiled.textContent).toContain('Reconstruct Kin');

    const button = compiled.querySelector('button') as HTMLButtonElement;
    button.click();
    await fixture.whenStable();
    fixture.detectChanges();

    expect(wrongMachineService.reconstruct).toHaveBeenCalledOnceWith('pig_kin');
    expect(compiled.textContent).toContain('Pig Kin reconstructed');
    expect(compiled.textContent).toContain('A reconstructed goblin has joined the warband.');
    expect(compiled.textContent).toContain('View Unit');
  });

  it('shows missing reconstruction requirements without allowing reconstruction', async () => {
    wrongMachineService.getReconstructions.and.resolveTo({
      ok: true,
      data: {
        feature_unlocked: true,
        reconstructions: [
          {
            lineage_slug: 'pig_kin',
            kin_slug: 'pig_kin',
            name: 'Pig Kin',
            description: 'Reconstruct the first goblin-kin lineage from Farm materials.',
            is_unlocked: false,
            can_reconstruct: false,
            cost: {
              raw_chaos: { quantity_required: 5, quantity_owned: 2, is_met: false },
              items: [
                { item_slug: 'pig_ear', quantity_required: 3, quantity_owned: 1, is_met: false },
                { item_slug: 'mudking_crown_fragment', quantity_required: 1, quantity_owned: 1, is_met: true },
              ],
            },
            missing: [
              { type: 'raw_chaos', quantity_missing: 3 },
              { type: 'item', item_slug: 'pig_ear', quantity_missing: 2 },
            ],
            grants: { lineage_slug: 'pig_kin', unit_type_slug: 'frontline_bruiser_t1', unit_count: 1 },
          },
        ],
      },
    });

    const fixture = TestBed.createComponent(WrongMachinePageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Recover 3 Raw Chaos, 2 Pig Ear');
    expect(compiled.textContent).toContain('Reconstruction blocked.');
    expect(compiled.textContent).toContain('2/5');
    expect(compiled.textContent).toContain('1/3');
    expect(compiled.textContent).toContain('Needed');

    const button = compiled.querySelector('button') as HTMLButtonElement;
    expect(button.disabled).toBeTrue();
    button.click();

    expect(wrongMachineService.reconstruct).not.toHaveBeenCalled();
  });

  it('shows the locked state when the feature has not been recovered', async () => {
    wrongMachineService.getReconstructions.and.resolveTo({
      ok: true,
      data: {
        feature_unlocked: false,
        reconstructions: [],
      },
    });

    const fixture = TestBed.createComponent(WrongMachinePageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('The Wrong Machine has not been recovered yet.');
    expect(wrongMachineService.reconstruct).not.toHaveBeenCalled();
  });
});
