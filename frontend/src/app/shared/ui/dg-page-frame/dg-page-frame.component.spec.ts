import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { DgPageFrameComponent } from './dg-page-frame.component';

@Component({
  standalone: true,
  imports: [DgPageFrameComponent],
  template: `
    <dg-page-frame eyebrow="HQ" title="Title" subtitle="Subtitle">
      <div class="projected">Body</div>
    </dg-page-frame>
  `,
})
class HostComponent {}

describe('DgPageFrameComponent', () => {
  it('renders header content and projected body', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
      providers: [provideRouter([])],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('HQ');
    expect(compiled.textContent).toContain('Title');
    expect(compiled.textContent).toContain('Subtitle');
    expect(compiled.textContent).toContain('Body');
  });

  it('renders clickable breadcrumbs when provided', async () => {
    @Component({
      standalone: true,
      imports: [DgPageFrameComponent],
      template: `
        <dg-page-frame
          title="Title"
          [breadcrumbs]="[
            { label: 'HQ', route: '/home' },
            { label: 'Shop' }
          ]"
        />
      `,
    })
    class BreadcrumbHostComponent {}

    await TestBed.configureTestingModule({
      imports: [BreadcrumbHostComponent],
      providers: [provideRouter([])],
    }).compileComponents();

    const fixture = TestBed.createComponent(BreadcrumbHostComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const links = compiled.querySelectorAll('.route-frame__breadcrumb-link');
    const current = compiled.querySelector('.route-frame__breadcrumb-current') as HTMLElement;

    expect(links.length).toBe(1);
    expect(links[0].textContent).toContain('HQ');
    expect(current.textContent).toContain('Shop');
  });
});
