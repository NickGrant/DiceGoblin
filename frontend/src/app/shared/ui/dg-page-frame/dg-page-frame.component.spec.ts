import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
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
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('HQ');
    expect(compiled.textContent).toContain('Title');
    expect(compiled.textContent).toContain('Subtitle');
    expect(compiled.textContent).toContain('Body');
  });
});
