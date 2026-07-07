import { Injectable } from '@angular/core';
import { findDialogueScript } from '../../dialogue/dialogue-script-registry';
import { DialogueLibraryDefinition, DialogueScript, DialogueTriggerContext } from '../../dialogue/dialogue.models';

@Injectable({ providedIn: 'root' })
export class DialogueService {
  private libraryPromise: Promise<DialogueLibraryDefinition> | null = null;

  async getDialogue(context: DialogueTriggerContext): Promise<DialogueScript | null> {
    const library = await this.loadLibrary();
    return findDialogueScript(library, context);
  }

  private async loadLibrary(): Promise<DialogueLibraryDefinition> {
    if (this.libraryPromise) {
      return this.libraryPromise;
    }

    this.libraryPromise = this.fetchLibrary();
    return this.libraryPromise;
  }

  private async fetchLibrary(): Promise<DialogueLibraryDefinition> {
    const response = await fetch('/assets/dialogue/dialogue-scripts.json', {
      headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
      throw new Error(`Unable to load dialogue scripts (${response.status}).`);
    }

    const payload = await response.json();
    const scripts = Array.isArray((payload as DialogueLibraryDefinition).scripts)
      ? (payload as DialogueLibraryDefinition).scripts
      : [];

    return { scripts };
  }
}
