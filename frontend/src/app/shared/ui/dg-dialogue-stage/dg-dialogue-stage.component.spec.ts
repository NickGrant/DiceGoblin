import { ComponentFixture, TestBed } from '@angular/core/testing';
import { DgDialogueStageComponent } from './dg-dialogue-stage.component';
import { DialogueScript } from '../../../core/dialogue/dialogue.models';

const TEST_SCRIPT: DialogueScript = {
  id: 'test-script',
  backgroundUrl: '/assets/ui/biome/farm.png',
  startStepId: 'intro',
  speakers: [
    { id: 'mudking', side: 'right', name: 'Mudking', portraitUrl: '/assets/ui/units/pig_mudking.png', party: 'enemy', role: 'npc' },
    { id: 'player', side: 'left', name: 'Ashback', portraitUrl: '/assets/ui/units/goblin_bruiser.png', party: 'player', role: 'player' },
  ],
  steps: [
    { id: 'intro', speakerId: 'mudking', text: 'Are you here to fight me', nextStepId: 'answer', choices: [] },
    {
      id: 'answer',
      speakerId: 'player',
      text: 'How do you answer?',
      nextStepId: null,
      choices: [
        { id: 'yes', label: 'yes', nextStepId: 'yes-response' },
        { id: 'no', label: 'no', nextStepId: 'no-response' },
        { id: 'maybe', label: 'maybe', nextStepId: 'maybe-response' },
      ],
    },
    { id: 'yes-response', speakerId: 'mudking', text: 'Good!', nextStepId: null, choices: [] },
    { id: 'no-response', speakerId: 'mudking', text: 'Too bad!', nextStepId: null, choices: [] },
    { id: 'maybe-response', speakerId: 'mudking', text: 'Gonna fight ya anyway!', nextStepId: null, choices: [] },
  ],
};

describe('DgDialogueStageComponent', () => {
  let fixture: ComponentFixture<DgDialogueStageComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DgDialogueStageComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(DgDialogueStageComponent);
    fixture.componentRef.setInput('script', TEST_SCRIPT);
    fixture.detectChanges();
  });

  it('advances with enter and resolves choices with keyboard controls', () => {
    const completeSpy = spyOn(fixture.componentInstance.complete, 'emit');

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
    fixture.detectChanges();

    expect(fixture.componentInstance.currentStep()?.id).toBe('answer');

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown' }));
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown' }));
    fixture.detectChanges();

    expect(fixture.componentInstance.selectedChoiceIndex()).toBe(2);

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
    fixture.detectChanges();

    expect(fixture.componentInstance.currentStep()?.id).toBe('maybe-response');
    expect(fixture.componentInstance.choiceHistory()).toEqual([{ stepId: 'answer', choiceId: 'maybe' }]);
    expect(fixture.componentInstance.visibleEntries().map((entry) => entry.step.id)).toEqual(['answer', 'maybe-response']);

    document.dispatchEvent(new KeyboardEvent('keydown', { key: ' ' }));
    fixture.detectChanges();

    expect(completeSpy).toHaveBeenCalledWith([{ stepId: 'answer', choiceId: 'maybe' }]);
  });

  it('keeps only the latest two dialogue messages visible', () => {
    const host: HTMLElement = fixture.nativeElement;

    expect(fixture.componentInstance.visibleEntries().map((entry) => entry.step.id)).toEqual(['intro']);
    expect(host.querySelectorAll('.dialogue-stage__message').length).toBe(1);

    fixture.componentInstance.advance();
    fixture.detectChanges();

    expect(fixture.componentInstance.visibleEntries().map((entry) => entry.step.id)).toEqual(['intro', 'answer']);
    expect(host.textContent).toContain('Are you here to fight me');
    expect(host.textContent).toContain('How do you answer?');

    fixture.componentInstance.selectChoice(0);
    fixture.componentInstance.chooseSelectedOption();
    fixture.detectChanges();

    expect(fixture.componentInstance.visibleEntries().map((entry) => entry.step.id)).toEqual(['answer', 'yes-response']);
    expect(host.querySelectorAll('.dialogue-stage__message').length).toBe(2);
    expect(host.textContent).not.toContain('Are you here to fight me');
    expect(host.textContent).toContain('How do you answer?');
    expect(host.textContent).toContain('Good!');
  });

  it('keeps player dialogue on the left and enemy dialogue on the right', () => {
    let host: HTMLElement = fixture.nativeElement;
    let messages = Array.from(host.querySelectorAll('.dialogue-stage__message')) as HTMLElement[];

    expect(messages[0].classList.contains('dialogue-stage__message--right')).toBeTrue();

    fixture.componentInstance.advance();
    fixture.detectChanges();

    host = fixture.nativeElement;
    messages = Array.from(host.querySelectorAll('.dialogue-stage__message')) as HTMLElement[];
    expect(messages[0].classList.contains('dialogue-stage__message--right')).toBeTrue();
    expect(messages[1].classList.contains('dialogue-stage__message--left')).toBeTrue();
  });

  it('supports selecting a choice with the mouse', () => {
    fixture.componentInstance.advance();
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const buttons = Array.from(host.querySelectorAll('.dialogue-stage__choice')) as HTMLButtonElement[];
    buttons[1].click();
    fixture.detectChanges();

    expect(fixture.componentInstance.currentStep()?.id).toBe('no-response');
    expect(host.textContent).toContain('Too bad!');
  });
});
