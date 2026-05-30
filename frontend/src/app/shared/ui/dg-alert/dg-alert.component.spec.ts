import { TestBed } from '@angular/core/testing';
import { DgAlertComponent } from './dg-alert.component';

describe('DgAlertComponent', () => {
  it('uses polite status semantics by default', () => {
    const fixture = TestBed.createComponent(DgAlertComponent);
    fixture.detectChanges();

    const alert = fixture.nativeElement.querySelector('.dg-alert') as HTMLDivElement;

    expect(alert.getAttribute('role')).toBe('status');
    expect(alert.getAttribute('aria-live')).toBe('polite');
    expect(alert.classList.contains('dg-alert--error')).toBeFalse();
    expect(alert.classList.contains('dg-alert--success')).toBeFalse();
  });

  it('uses assertive alert semantics for error tone', () => {
    const fixture = TestBed.createComponent(DgAlertComponent);
    fixture.componentRef.setInput('tone', 'error');
    fixture.detectChanges();

    const alert = fixture.nativeElement.querySelector('.dg-alert') as HTMLDivElement;

    expect(alert.getAttribute('role')).toBe('alert');
    expect(alert.getAttribute('aria-live')).toBe('assertive');
    expect(alert.classList.contains('dg-alert--error')).toBeTrue();
  });

  it('applies the success tone class', () => {
    const fixture = TestBed.createComponent(DgAlertComponent);
    fixture.componentRef.setInput('tone', 'success');
    fixture.detectChanges();

    const alert = fixture.nativeElement.querySelector('.dg-alert') as HTMLDivElement;

    expect(alert.classList.contains('dg-alert--success')).toBeTrue();
    expect(alert.getAttribute('role')).toBe('status');
  });
});
