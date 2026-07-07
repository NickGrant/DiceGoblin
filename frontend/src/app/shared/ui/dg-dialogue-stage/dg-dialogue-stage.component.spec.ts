import { ComponentFixture, TestBed } from '@angular/core/testing';
import { DgDialogueStageComponent } from './dg-dialogue-stage.component';
import { DialogueScript } from '../../../core/dialogue/dialogue.models';

const TEST_SCRIPT: DialogueScript = {
  id: 'test-script',
  backgroundUrl: '/assets/ui/biome/farm.png',
  startStepId: 'intro',
  speakers: [
    { id: 'mudking', side: 'left', name: 'Mudking', portraitUrl: '/assets/ui/units/pig_mudking.png', role: 'npc' },
    { id: 'player', side: 'right', name: 'Ashback', portraitUrl: '/assets/ui/units/goblin_bruiser.png', role: 'player' },
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

    document.dispatchEvent(new KeyboardEvent('keydown', { key: ' ' }));
    fixture.detectChanges();

    expect(completeSpy).toHaveBeenCalledWith([{ stepId: 'answer', choiceId: 'maybe' }]);
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
