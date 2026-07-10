import { TestBed } from '@angular/core/testing';
import { ViewportOrientationService } from './viewport-orientation.service';

describe('ViewportOrientationService', () => {
  let originalInnerWidth: number;
  let originalInnerHeight: number;
  let matchMediaSpy: jasmine.Spy<(query: string) => MediaQueryList>;

  beforeEach(() => {
    originalInnerWidth = window.innerWidth;
    originalInnerHeight = window.innerHeight;
    matchMediaSpy = spyOn(window, 'matchMedia').and.callFake((query: string) => ({
      matches: query.includes('pointer: coarse'),
      media: query,
      onchange: null,
      addListener: () => undefined,
      removeListener: () => undefined,
      addEventListener: () => undefined,
      removeEventListener: () => undefined,
      dispatchEvent: () => false,
    }));

    TestBed.configureTestingModule({});
  });

  afterEach(() => {
    Object.defineProperty(window, 'innerWidth', { configurable: true, writable: true, value: originalInnerWidth });
    Object.defineProperty(window, 'innerHeight', { configurable: true, writable: true, value: originalInnerHeight });
  });

  it('requires landscape for coarse-pointer phone-sized portrait viewports', () => {
    Object.defineProperty(window, 'innerWidth', { configurable: true, writable: true, value: 390 });
    Object.defineProperty(window, 'innerHeight', { configurable: true, writable: true, value: 844 });

    const service = TestBed.inject(ViewportOrientationService);
    service.initialize();

    expect(service.requiresLandscape()).toBeTrue();
    expect(service.isLandscapeGateActive()).toBeTrue();
  });

  it('does not gate landscape phone viewports', () => {
    Object.defineProperty(window, 'innerWidth', { configurable: true, writable: true, value: 844 });
    Object.defineProperty(window, 'innerHeight', { configurable: true, writable: true, value: 390 });

    const service = TestBed.inject(ViewportOrientationService);
    service.initialize();

    expect(service.requiresLandscape()).toBeFalse();
  });

  it('does not gate tablet-sized portrait viewports', () => {
    Object.defineProperty(window, 'innerWidth', { configurable: true, writable: true, value: 768 });
    Object.defineProperty(window, 'innerHeight', { configurable: true, writable: true, value: 1024 });

    const service = TestBed.inject(ViewportOrientationService);
    service.initialize();

    expect(service.requiresLandscape()).toBeFalse();
  });

  it('does not gate narrow desktop windows without coarse pointer input', () => {
    matchMediaSpy.and.callFake((query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addListener: () => undefined,
      removeListener: () => undefined,
      addEventListener: () => undefined,
      removeEventListener: () => undefined,
      dispatchEvent: () => false,
    }));
    Object.defineProperty(window, 'innerWidth', { configurable: true, writable: true, value: 390 });
    Object.defineProperty(window, 'innerHeight', { configurable: true, writable: true, value: 844 });

    const service = TestBed.inject(ViewportOrientationService);
    service.initialize();

    expect(service.requiresLandscape()).toBeFalse();
  });
});
