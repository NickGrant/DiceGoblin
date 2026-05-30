import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { DgCommandBtnDirective } from './dg-command-btn.directive';

@Component({
  standalone: true,
  imports: [DgCommandBtnDirective],
  template: `
    <button dgCommandBtn="primary" class="primary-btn">Action</button>
    <a dgCommandBtn="muted" class="muted-link">Link</a>
  `,
})
class HostComponent {}

describe('DgCommandBtnDirective', () => {
  it('applies shared command-button classes to buttons and links', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const primary = fixture.nativeElement.querySelector('.primary-btn') as HTMLElement;
    const muted = fixture.nativeElement.querySelector('.muted-link') as HTMLElement;

    expect(primary.classList.contains('dg-command-btn')).toBeTrue();
    expect(primary.classList.contains('dg-command-btn--primary')).toBeTrue();
    expect(muted.classList.contains('dg-command-btn')).toBeTrue();
    expect(muted.classList.contains('dg-command-btn--muted')).toBeTrue();
  });
});
