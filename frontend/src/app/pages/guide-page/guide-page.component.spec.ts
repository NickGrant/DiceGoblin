import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { GuidePageComponent } from './guide-page.component';

describe('GuidePageComponent', () => {
  it('renders the public guide sections', async () => {
    await TestBed.configureTestingModule({
      imports: [GuidePageComponent],
      providers: [provideRouter([])],
    }).compileComponents();

    const fixture = TestBed.createComponent(GuidePageComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('How Dice Goblins Works');
    expect(text).toContain('Available Unlocks');
    expect(text).toContain('Units');
    expect(text).toContain('How Promotion Works');
    expect(text).toContain('How Runs Work');
  });
});
