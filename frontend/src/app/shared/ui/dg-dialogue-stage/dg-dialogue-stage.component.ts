import { Component, EventEmitter, HostListener, Output, computed, effect, input, signal } from '@angular/core';
import { DialogueChoiceSelection, DialogueScript, DialogueSpeaker, DialogueStep } from '../../../core/dialogue/dialogue.models';

type DialogueVisibleEntry = {
  step: DialogueStep;
  speaker: DialogueSpeaker | null;
  isCurrent: boolean;
};

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
  readonly visibleStepIds = signal<string[]>([]);
  readonly choiceHistory = signal<DialogueChoiceSelection[]>([]);
  readonly currentStep = computed<DialogueStep | null>(
    () => this.script()?.steps.find((step) => step.id === this.currentStepId()) ?? null,
  );
  readonly visibleEntries = computed<DialogueVisibleEntry[]>(() =>
    this.visibleStepIds()
      .map((stepId) => this.stepById(stepId))
      .filter((step): step is DialogueStep => step !== null)
      .map((step) => ({
        step,
        speaker: this.speakerById(step.speakerId),
        isCurrent: step.id === this.currentStepId(),
      })),
  );
  readonly activeSpeaker = computed<DialogueSpeaker | null>(
    () => this.speakerById(this.currentStep()?.speakerId ?? null),
  );
  readonly hasChoices = computed(() => (this.currentStep()?.choices.length ?? 0) > 0);
  readonly canAdvance = computed(() => !this.hasChoices());
  readonly selectedChoice = computed(() => this.currentStep()?.choices[this.selectedChoiceIndex()] ?? null);

  constructor() {
    effect(() => {
      const script = this.script();
      if (!script) {
        this.currentStepId.set('');
        this.visibleStepIds.set([]);
        this.selectedChoiceIndex.set(0);
        this.choiceHistory.set([]);
        return;
      }

      this.currentStepId.set(script.startStepId);
      this.visibleStepIds.set(script.startStepId ? [script.startStepId] : []);
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

    this.moveToStep(step.nextStepId);
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
    this.moveToStep(choice.nextStepId);
  }

  private moveChoice(delta: number): void {
    const choices = this.currentStep()?.choices ?? [];
    if (!choices.length) {
      return;
    }

    const nextIndex = (this.selectedChoiceIndex() + delta + choices.length) % choices.length;
    this.selectedChoiceIndex.set(nextIndex);
  }

  private moveToStep(stepId: string): void {
    this.currentStepId.set(stepId);
    this.visibleStepIds.set([...this.visibleStepIds(), stepId].slice(-2));
    this.selectedChoiceIndex.set(0);
  }

  private stepById(stepId: string | null): DialogueStep | null {
    if (!stepId) {
      return null;
    }

    return this.script()?.steps.find((step) => step.id === stepId) ?? null;
  }

  private speakerById(speakerId: string | null): DialogueSpeaker | null {
    if (!speakerId) {
      return null;
    }

    return this.script()?.speakers.find((speaker) => speaker.id === speakerId) ?? null;
  }
}
