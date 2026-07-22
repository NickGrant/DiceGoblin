import { ComponentFixture, TestBed, fakeAsync, tick } from '@angular/core/testing';
import { DgDialogueStageComponent } from './dg-dialogue-stage.component';
import { DialogueScript } from '../../../core/dialogue/dialogue.models';

const TEST_SCRIPT: DialogueScript = {
  id: 'test-script',
  backgroundUrl: '/assets/ui/biome/farm.png',
  startStepId: 'intro',
  speakers: [
    { id: 'mudking', side: 'right', name: 'Mudking', portraitUrl: '/assets/ui/units/animated/pig/mudking/frame_0.png', party: 'enemy', role: 'npc' },
    { id: 'player', side: 'left', name: 'Ashback', portraitUrl: '/assets/ui/units/animated/goblin/base/frame_0.png', party: 'player', role: 'player' },
  ],
  steps: [
    { id: 'intro', speakerId: 'mudking', text: 'Are you here to fight me', nextStepId: 'answer', choices: [], enterEffect: null },
    {
      id: 'answer',
      speakerId: 'player',
      text: 'How do you answer?',
      nextStepId: null,
      enterEffect: null,
      choices: [
        { id: 'yes', label: 'yes', nextStepId: 'yes-response' },
        { id: 'no', label: 'no', nextStepId: 'no-response' },
        { id: 'maybe', label: 'maybe', nextStepId: 'maybe-response' },
      ],
    },
    { id: 'yes-response', speakerId: 'mudking', text: 'Good!', nextStepId: null, choices: [], enterEffect: null },
    { id: 'no-response', speakerId: 'mudking', text: 'Too bad!', nextStepId: null, choices: [], enterEffect: null },
    { id: 'maybe-response', speakerId: 'mudking', text: 'Gonna fight ya anyway!', nextStepId: null, choices: [], enterEffect: null },
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

  it('appends the selected choice text to the prompt bubble after choosing', () => {
    fixture.componentInstance.advance();
    fixture.detectChanges();

    fixture.componentInstance.selectChoice(2);
    fixture.componentInstance.chooseSelectedOption();
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const promptBubble = Array.from(host.querySelectorAll('.dialogue-stage__text'))
      .find((element) => element.textContent?.includes('How do you answer?')) as HTMLElement | undefined;

    expect(promptBubble).toBeDefined();
    expect(promptBubble?.textContent).toContain('How do you answer?');
    expect(promptBubble?.textContent).toContain('maybe');
  });

  it('runs a player reveal effect before showing choices and updates the portrait', fakeAsync(() => {
    const revealScript: DialogueScript = {
      id: 'reveal-script',
      backgroundUrl: '/assets/ui/biome/mystic_cave.png',
      startStepId: 'reveal',
      speakers: [
        { id: 'player', side: 'left', name: 'Ashback', portraitUrl: '/assets/ui/units/animated/goblin/primordial/frame_0.png', party: 'player', role: 'player' },
      ],
      steps: [
        {
          id: 'reveal',
          speakerId: 'player',
          text: '...',
          nextStepId: null,
          choices: [{ id: 'yes', label: 'Yes', nextStepId: 'done' }],
          enterEffect: {
            kind: 'player_reveal',
            initialOverlayUrl: '/assets/ui/units/animated/goblin/primordial/frame_0.png',
            finalOverlayUrl: '/assets/ui/units/animated/goblin/base/frame_0.png',
            resultingPlayerPortraitUrl: '/assets/ui/units/animated/goblin/base/frame_0.png',
            initialDurationMs: 10,
            flashCount: 1,
            flashIntervalMs: 10,
            betweenOverlaysMs: 10,
            finalHoldMs: 10,
          },
        },
        { id: 'done', speakerId: 'player', text: 'Done', nextStepId: null, choices: [], enterEffect: null },
      ],
    };

    fixture.componentRef.setInput('script', revealScript);
    fixture.detectChanges();

    expect(fixture.componentInstance.hasVisibleChoices()).toBeFalse();
    expect(fixture.componentInstance.overlayImageUrl()).toBe('/assets/ui/units/animated/goblin/primordial/frame_0.png');

    tick(120);
    fixture.detectChanges();

    expect(fixture.componentInstance.hasVisibleChoices()).toBeTrue();
    expect(fixture.componentInstance.overlayImageUrl()).toBeNull();

    const playerSpeaker = fixture.componentInstance.visibleEntries()[0]?.speaker;
    expect(playerSpeaker?.portraitUrl).toBe('/assets/ui/units/animated/goblin/base/frame_0.png');
  }));
});
