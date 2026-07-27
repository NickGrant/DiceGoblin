import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { SessionService } from '../../core/services/session/session.service';
import { GuidePageComponent } from './guide-page.component';

class SessionServiceStub {
  readonly session = signal({
    isAuthenticated: false,
    displayName: 'Visitor',
    userId: null,
    csrfToken: null,
  });
  readonly initialize = jasmine.createSpy('initialize').and.resolveTo();
}

describe('GuidePageComponent', () => {
  let sessionService: SessionServiceStub;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [GuidePageComponent],
      providers: [
        provideRouter([]),
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();
    sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
  });

  it('renders a scannable field manual with the core guide sections', () => {
    const fixture = TestBed.createComponent(GuidePageComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Guide');
    expect(text).toContain('Field Manual');
    expect(text).toContain('Quick Reference');
    expect(text).toContain('How To Play');
    expect(text).toContain('Base Loop');
    expect(text).toContain('Combat Stats');
    expect(text).toContain('Starter classes and role lanes');
    expect(text).toContain('Action order');
    expect(text).toContain('Damage flow');
    expect(text).toContain('Read size, material, and affixes together');
    expect(text).toContain('Frontline');
    expect(text).toContain('Bruiser');
    expect(text).toContain('Marksman');
    expect(text).not.toContain('Guardian');
    expect(text).not.toContain('Bannerbearer');
    expect(text).not.toContain('Saboteur');
    expect(text).toContain('Tier 1');
    expect(text).toContain('Map Glossary');
    expect(text).toContain('Shrine');
    expect(text).toContain('Hazard');
    expect(text).toContain('Chaos');
    expect(text).toContain('Dialogue');
    expect(text).toContain('Read the route before spending energy');
    expect(text).not.toContain('Authenticated Codex');
    expect(text).not.toContain('Affix Archive');
  });

  it('scrolls to guide sections from sidenav links', () => {
    const fixture = TestBed.createComponent(GuidePageComponent);
    fixture.detectChanges();
    const target = fixture.nativeElement.querySelector('#guide-map') as HTMLElement;
    const scrollSpy = spyOn(target, 'scrollIntoView');
    const event = new MouseEvent('click');
    spyOn(event, 'preventDefault');

    (fixture.componentInstance as any).scrollToGuideSection('guide-map', event);

    expect(event.preventDefault).toHaveBeenCalled();
    expect(scrollSpy).toHaveBeenCalledWith({ behavior: 'smooth', block: 'start' });
  });

  it('initializes session state on init', () => {
    const fixture = TestBed.createComponent(GuidePageComponent);
    fixture.detectChanges();

    expect(sessionService.initialize).toHaveBeenCalled();
  });
});
