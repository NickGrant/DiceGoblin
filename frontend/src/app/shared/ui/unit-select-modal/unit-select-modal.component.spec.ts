import { Component, signal } from '@angular/core';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { UnitRecord } from '../../../core/models/api.models';
import { UnitSelectModalComponent } from './unit-select-modal.component';

@Component({
  standalone: true,
  imports: [UnitSelectModalComponent],
  template: `
    <dg-unit-select-modal
      [open]="true"
      [units]="units"
      [lockedFilters]="lockedFilters()"
      [hiddenFilters]="hiddenFilters"
      (selected)="selected.set($event)"
    />
  `,
})
class UnitSelectModalHostComponent {
  units: UnitRecord[] = [
    { id: 'u1', name: 'Gnashtusk', level: 10, unit_type_slug: 'bruiser', unit_type_name: 'Bruiser', kin_slug: 'pig_kin', kin_name: 'Pig Kin', tier: 3 },
    { id: 'u2', name: 'Sooteye', level: 4, unit_type_slug: 'marksman', unit_type_name: 'Marksman', kin_slug: 'goblin', kin_name: 'Goblin', tier: 1 },
  ];
  hiddenFilters = ['unitType'] as const;
  lockedFilters = signal({ unitType: 'bruiser' });
  selected = signal<UnitRecord | null>(null);
}

describe('UnitSelectModalComponent', () => {
  let fixture: ComponentFixture<UnitSelectModalHostComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [UnitSelectModalHostComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(UnitSelectModalHostComponent);
    fixture.detectChanges();
  });

  it('applies hidden locked unit type filters', () => {
    const text = fixture.nativeElement.textContent as string;

    expect(text).toContain('Gnashtusk');
    expect(text).not.toContain('Sooteye');
    expect(fixture.debugElement.query(By.css('select[aria-label="Unit Type"]'))).toBeNull();
  });

  it('emits the confirmed selected unit', () => {
    const card = fixture.debugElement.query(By.css('.unit-card'));
    card.nativeElement.click();
    fixture.detectChanges();

    const selectButton = fixture.debugElement.query(By.css('.select-modal__actions button:last-child'));
    selectButton.nativeElement.click();

    expect(fixture.componentInstance.selected()?.id).toBe('u1');
  });
});
