import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { PageFrameComponent } from './page-frame.component';

@Component({
  standalone: true,
  imports: [PageFrameComponent],
  template: `
    <page-frame title="Title" subtitle="Subtitle">
      <div class="projected">Body</div>
    </page-frame>
  `,
})
class HostComponent {}

describe('PageFrameComponent', () => {
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
    expect(compiled.querySelector('page-frame header')).toBeNull();
  });

  it('renders clickable breadcrumbs when provided', async () => {
    @Component({
      standalone: true,
      imports: [PageFrameComponent],
      template: `
        <page-frame
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
    const links = compiled.querySelectorAll('nav.page-hero__breadcrumbs a');
    const current = compiled.querySelector('nav.page-hero__breadcrumbs [aria-current="page"]') as HTMLElement;

    expect(links.length).toBe(1);
    expect(links[0].textContent).toContain('HQ');
    expect(current.textContent).toContain('Shop');
  });

  it('does not add an HQ crumb on the home page', async () => {
    @Component({
      standalone: true,
      imports: [PageFrameComponent],
      template: `
        <page-frame
          title="Title"
          [breadcrumbs]="[{ label: 'Home' }]"
        />
      `,
    })
    class HomeBreadcrumbHostComponent {}

    await TestBed.configureTestingModule({
      imports: [HomeBreadcrumbHostComponent],
      providers: [provideRouter([])],
    }).compileComponents();

    const fixture = TestBed.createComponent(HomeBreadcrumbHostComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const links = compiled.querySelectorAll('nav.page-hero__breadcrumbs a');
    const current = compiled.querySelector('nav.page-hero__breadcrumbs [aria-current="page"]') as HTMLElement;

    expect(links.length).toBe(0);
    expect(current.textContent).toContain('Home');
    expect(compiled.textContent).not.toContain('HQ');
  });
});
