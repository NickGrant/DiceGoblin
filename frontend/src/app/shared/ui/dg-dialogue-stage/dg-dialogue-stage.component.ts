import { Component, EventEmitter, HostListener, Output, computed, effect, input, signal } from '@angular/core';
import {
  DialogueChoiceSelection,
  DialogueScript,
  DialogueSpeaker,
  DialogueStep,
  DialogueStepEnterEffect,
} from '../../../core/dialogue/dialogue.models';

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
  readonly speakerPortraitOverrides = signal<Record<string, string>>({});
  readonly overlayVisible = signal(false);
  readonly overlayFlashing = signal(false);
  readonly overlayImageUrl = signal<string | null>(null);
  readonly stepEffectInProgress = signal(false);
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
  readonly visibleChoices = computed(() => (this.stepEffectInProgress() ? [] : (this.currentStep()?.choices ?? [])));
  readonly hasVisibleChoices = computed(() => this.visibleChoices().length > 0);
  readonly canAdvance = computed(() => !this.hasChoices() && !this.stepEffectInProgress());
  readonly selectedChoice = computed(() => this.currentStep()?.choices[this.selectedChoiceIndex()] ?? null);
  private effectRunToken = 0;

  constructor() {
    effect(() => {
      const script = this.script();
      this.effectRunToken += 1;
      if (!script) {
        this.currentStepId.set('');
        this.visibleStepIds.set([]);
        this.selectedChoiceIndex.set(0);
        this.choiceHistory.set([]);
        this.speakerPortraitOverrides.set({});
        this.clearOverlay();
        this.stepEffectInProgress.set(false);
        return;
      }

      this.currentStepId.set(script.startStepId);
      this.visibleStepIds.set(script.startStepId ? [script.startStepId] : []);
      this.selectedChoiceIndex.set(0);
      this.choiceHistory.set([]);
      this.speakerPortraitOverrides.set({});
      this.clearOverlay();
      this.stepEffectInProgress.set(false);
    });

    effect(() => {
      const step = this.currentStep();
      const token = ++this.effectRunToken;
      this.stepEffectInProgress.set(false);
      this.clearOverlay();
      if (!step?.enterEffect) {
        return;
      }

      void this.runEnterEffect(step.enterEffect, token);
    });
  }

  @HostListener('document:keydown', ['$event'])
  handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowUp' && this.hasVisibleChoices()) {
      event.preventDefault();
      this.moveChoice(-1);
      return;
    }

    if (event.key === 'ArrowDown' && this.hasVisibleChoices()) {
      event.preventDefault();
      this.moveChoice(1);
      return;
    }

    if (event.key === 'Enter') {
      event.preventDefault();
      if (this.hasVisibleChoices()) {
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
    if (!step || step.choices.length > 0 || this.stepEffectInProgress()) {
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
    if (!step || !choice || this.stepEffectInProgress()) {
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

  displayedStepText(step: DialogueStep): string {
    const selectedChoice = this.selectedChoiceLabelForStep(step.id);
    return selectedChoice ? `${step.text}\n${selectedChoice}` : step.text;
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
    this.effectRunToken += 1;
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

    const speaker = this.script()?.speakers.find((entry) => entry.id === speakerId) ?? null;
    if (!speaker) {
      return null;
    }

    const overrideUrl = this.speakerPortraitOverrides()[speakerId] ?? null;
    return overrideUrl ? { ...speaker, portraitUrl: overrideUrl } : speaker;
  }

  private selectedChoiceLabelForStep(stepId: string): string | null {
    const choiceId = this.choiceHistory().find((selection) => selection.stepId === stepId)?.choiceId ?? null;
    if (!choiceId) {
      return null;
    }

    const step = this.stepById(stepId);
    return step?.choices.find((choice) => choice.id === choiceId)?.label ?? null;
  }

  private async runEnterEffect(effect: DialogueStepEnterEffect, token: number): Promise<void> {
    if (effect.kind !== 'player_reveal') {
      return;
    }

    this.stepEffectInProgress.set(true);
    this.overlayImageUrl.set(effect.initialOverlayUrl);
    this.overlayVisible.set(true);
    await this.delay(effect.initialDurationMs);
    if (token !== this.effectRunToken) {
      return;
    }

    for (let index = 0; index < effect.flashCount; index += 1) {
      this.overlayFlashing.set(true);
      await this.delay(effect.flashIntervalMs);
      if (token !== this.effectRunToken) {
        return;
      }
      this.overlayFlashing.set(false);
      await this.delay(effect.flashIntervalMs);
      if (token !== this.effectRunToken) {
        return;
      }
    }

    this.overlayVisible.set(false);
    await this.delay(effect.betweenOverlaysMs);
    if (token !== this.effectRunToken) {
      return;
    }

    this.overlayImageUrl.set(effect.finalOverlayUrl);
    this.overlayVisible.set(true);
    this.speakerPortraitOverrides.set({
      ...this.speakerPortraitOverrides(),
      player: effect.resultingPlayerPortraitUrl,
    });
    await this.delay(effect.finalHoldMs);
    if (token !== this.effectRunToken) {
      return;
    }

    this.clearOverlay();
    this.stepEffectInProgress.set(false);
  }

  private clearOverlay(): void {
    this.overlayVisible.set(false);
    this.overlayFlashing.set(false);
    this.overlayImageUrl.set(null);
  }

  private delay(durationMs: number): Promise<void> {
    return new Promise((resolve) => window.setTimeout(resolve, durationMs));
  }
}
