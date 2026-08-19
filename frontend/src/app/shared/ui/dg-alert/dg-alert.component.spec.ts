import { TestBed } from '@angular/core/testing';
import { DgAlertComponent } from './dg-alert.component';

describe('DgAlertComponent', () => {
  it('uses polite status semantics by default', () => {
    const fixture = TestBed.createComponent(DgAlertComponent);
    fixture.detectChanges();

    const alert = fixture.nativeElement as HTMLElement;

    expect(alert.getAttribute('role')).toBe('status');
    expect(alert.getAttribute('aria-live')).toBe('polite');
    expect(alert.getAttribute('data-dg-alert')).toBe('info');
  });

  it('uses assertive alert semantics for error tone', () => {
    const fixture = TestBed.createComponent(DgAlertComponent);
    fixture.componentRef.setInput('tone', 'error');
    fixture.detectChanges();

    const alert = fixture.nativeElement as HTMLElement;

    expect(alert.getAttribute('role')).toBe('alert');
    expect(alert.getAttribute('aria-live')).toBe('assertive');
    expect(alert.getAttribute('data-dg-alert')).toBe('error');
  });

  it('applies the success tone class', () => {
    const fixture = TestBed.createComponent(DgAlertComponent);
    fixture.componentRef.setInput('tone', 'success');
    fixture.detectChanges();

    const alert = fixture.nativeElement as HTMLElement;

    expect(alert.getAttribute('data-dg-alert')).toBe('success');
    expect(alert.getAttribute('role')).toBe('status');
  });
});
