import { Component } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { HorizontalRailDirective } from './horizontal-rail.directive';

@Component({
  standalone: true,
  imports: [HorizontalRailDirective],
  template: `<div dgHorizontalRail class="rail"></div>`,
})
class HostComponent {}

describe('HorizontalRailDirective', () => {
  function mockOverflowingRail(rail: HTMLElement, initialScrollLeft = 0): void {
    let scrollLeft = initialScrollLeft;

    Object.defineProperties(rail, {
      clientWidth: { configurable: true, value: 200 },
      scrollWidth: { configurable: true, value: 600 },
      scrollLeft: {
        configurable: true,
        get: () => scrollLeft,
        set: (value: number) => {
          scrollLeft = value;
        },
      },
    });
  }

  it('adds tabindex when missing', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const rail = fixture.nativeElement.querySelector('.rail') as HTMLElement;
    expect(rail.getAttribute('tabindex')).toBe('0');
  });

  it('remaps wheel scrolling to horizontal scroll', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const rail = fixture.nativeElement.querySelector('.rail') as HTMLElement;
    mockOverflowingRail(rail);

    const event = new WheelEvent('wheel', { deltaY: 120, cancelable: true });
    rail.dispatchEvent(event);

    expect(rail.scrollLeft).toBe(120);
  });

  it('supports keyboard horizontal navigation', async () => {
    await TestBed.configureTestingModule({
      imports: [HostComponent],
    }).compileComponents();

    const fixture = TestBed.createComponent(HostComponent);
    fixture.detectChanges();

    const rail = fixture.nativeElement.querySelector('.rail') as HTMLElement;
    mockOverflowingRail(rail);

    rail.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', cancelable: true }));
    expect(rail.scrollLeft).toBe(80);
  });
});
