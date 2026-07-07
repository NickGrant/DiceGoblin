import { Component, EventEmitter, HostListener, Output, computed, effect, input, signal } from '@angular/core';
import { DialogueChoiceSelection, DialogueScript, DialogueSpeaker, DialogueStep } from '../../../core/dialogue/dialogue.models';

@Component({
  selector: 'dg-dialogue-stage',
  standalone: true,
  templateUrl: './dg-dialogue-stage.component.html',
  styleUrl: './dg-dialogue-stage.component.scss',
})
export class DgDialogueStageComponent {
  readonly script = input<DialogueScript | null>(null);
  @Output() readonly complete = new EventEmitter<DialogueChoiceSelection[]>();

  readonly selectedChoiceIndex = signal(0);
  readonly currentStepId = signal('');
  readonly choiceHistory = signal<DialogueChoiceSelection[]>([]);
  readonly currentStep = computed<DialogueStep | null>(
    () => this.script()?.steps.find((step) => step.id === this.currentStepId()) ?? null,
  );
  readonly leftSpeaker = computed<DialogueSpeaker | null>(
    () => this.script()?.speakers.find((speaker) => speaker.side === 'left') ?? null,
  );
  readonly rightSpeaker = computed<DialogueSpeaker | null>(
    () => this.script()?.speakers.find((speaker) => speaker.side === 'right') ?? null,
  );
  readonly activeSpeaker = computed<DialogueSpeaker | null>(
    () => this.script()?.speakers.find((speaker) => speaker.id === this.currentStep()?.speakerId) ?? null,
  );
  readonly hasChoices = computed(() => (this.currentStep()?.choices.length ?? 0) > 0);
  readonly canAdvance = computed(() => !this.hasChoices());
  readonly selectedChoice = computed(() => this.currentStep()?.choices[this.selectedChoiceIndex()] ?? null);

  constructor() {
    effect(() => {
      const script = this.script();
      if (!script) {
        this.currentStepId.set('');
        this.selectedChoiceIndex.set(0);
        this.choiceHistory.set([]);
        return;
      }

      this.currentStepId.set(script.startStepId);
      this.selectedChoiceIndex.set(0);
      this.choiceHistory.set([]);
    });
  }

  @HostListener('document:keydown', ['$event'])
  handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowUp' && this.hasChoices()) {
      event.preventDefault();
      this.moveChoice(-1);
      return;
    }

    if (event.key === 'ArrowDown' && this.hasChoices()) {
      event.preventDefault();
      this.moveChoice(1);
      return;
    }

    if (event.key === 'Enter') {
      event.preventDefault();
      if (this.hasChoices()) {
        this.chooseSelectedOption();
      } else {
        this.advance();
      }
      return;
    }

    if (event.key === ' ' && this.canAdvance()) {
      event.preventDefault();
      this.advance();
    }
  }

  advance(): void {
    const step = this.currentStep();
    if (!step || step.choices.length > 0) {
      return;
    }

    if (!step.nextStepId) {
      this.complete.emit(this.choiceHistory());
      return;
    }

    this.currentStepId.set(step.nextStepId);
    this.selectedChoiceIndex.set(0);
  }

  selectChoice(index: number): void {
    this.selectedChoiceIndex.set(index);
  }

  chooseSelectedOption(): void {
    const step = this.currentStep();
    const choice = this.selectedChoice();
    if (!step || !choice) {
      return;
    }

    this.choiceHistory.set([
      ...this.choiceHistory(),
      {
        stepId: step.id,
        choiceId: choice.id,
      },
    ]);
    this.currentStepId.set(choice.nextStepId);
    this.selectedChoiceIndex.set(0);
  }

  activeTextFor(speaker: DialogueSpeaker | null): string {
    if (!speaker || this.activeSpeaker()?.id !== speaker.id) {
      return '';
    }

    return this.currentStep()?.text ?? '';
  }

  isSpeakerActive(speaker: DialogueSpeaker | null): boolean {
    return !!speaker && this.activeSpeaker()?.id === speaker.id;
  }

  private moveChoice(delta: number): void {
    const choices = this.currentStep()?.choices ?? [];
    if (!choices.length) {
      return;
    }

    const nextIndex = (this.selectedChoiceIndex() + delta + choices.length) % choices.length;
    this.selectedChoiceIndex.set(nextIndex);
  }
}
