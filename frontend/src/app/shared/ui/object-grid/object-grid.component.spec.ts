import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { GridObjectComponent } from '../grid-object/grid-object.component';
import { ObjectGridComponent } from './object-grid.component';

@Component({
  standalone: true,
  template: `{{ object().name }}`,
})
class StubGridObjectComponent extends GridObjectComponent<{ id: string; name: string }> {}

@Component({
  standalone: true,
  imports: [ObjectGridComponent],
  template: `
    <dg-object-grid
      [objects]="objects"
      [objectComponent]="objectComponent"
      [pageSize]="2"
      emptyMessage="No objects."
    />
  `,
})
class HostComponent {
  readonly objectComponent = StubGridObjectComponent;
  readonly objects = [
    { id: '1', name: 'One' },
    { id: '2', name: 'Two' },
    { id: '3', name: 'Three' },
  ];
}

@Component({
  standalone: true,
  imports: [ObjectGridComponent],
  template: `
    <dg-object-grid
      [objects]="objects"
      [objectComponent]="objectComponent"
      [leadingTemplate]="leadingTile"
      [pageSize]="3"
      emptyMessage="No objects."
    />

    <ng-template #leadingTile>
      <div>Leading Tile</div>
    </ng-template>
  `,
})
class LeadingTileHostComponent {
  readonly objectComponent = StubGridObjectComponent;
  readonly objects = [
    { id: '1', name: 'One' },
    { id: '2', name: 'Two' },
    { id: '3', name: 'Three' },
  ];
}

describe('ObjectGridComponent', () => {
  it('renders paginated objects and advances to the next page', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    let compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('One');
    expect(compiled.textContent).toContain('Two');
    expect(compiled.textContent).not.toContain('Three');

    const nextButton = Array.from(compiled.querySelectorAll('button')).find((button) =>
      button.textContent?.includes('Next'),
    ) as HTMLButtonElement;
    nextButton.click();
    fixture.detectChanges();

    compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Three');
  });

  it('reserves the first slot for a leading tile on every page', async () => {
    await TestBed.configureTestingModule({
      imports: [LeadingTileHostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(LeadingTileHostComponent);
    fixture.detectChanges();

    let compiled = fixture.nativeElement as HTMLElement;
    const grid = fixture.debugElement.query(By.directive(ObjectGridComponent)).componentInstance as ObjectGridComponent;

    expect(grid.objectsPerPage()).toBe(2);
    expect(compiled.textContent).toContain('Leading Tile');
    expect(compiled.textContent).toContain('One');
    expect(compiled.textContent).toContain('Two');
    expect(compiled.textContent).not.toContain('Three');

    const nextButton = Array.from(compiled.querySelectorAll('button')).find((button) =>
      button.textContent?.includes('Next'),
    ) as HTMLButtonElement;
    nextButton.click();
    fixture.detectChanges();

    compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Leading Tile');
    expect(compiled.textContent).toContain('Three');
  });
});
