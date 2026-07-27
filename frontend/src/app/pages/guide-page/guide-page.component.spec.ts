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
    expect(text).toContain('Read size, material, and affixes together');
    expect(text).toContain('Frontline');
    expect(text).toContain('Tier 1');
    expect(text).toContain('Map Glossary');
    expect(text).toContain('Read the route before spending energy');
    expect(text).not.toContain('Authenticated Codex');
    expect(text).not.toContain('Affix Archive');
  });

  it('initializes session state on init', () => {
    const fixture = TestBed.createComponent(GuidePageComponent);
    fixture.detectChanges();

    expect(sessionService.initialize).toHaveBeenCalled();
  });
});
