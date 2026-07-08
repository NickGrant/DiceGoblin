import { Injectable } from '@angular/core';
import { findDialogueScript } from '../../dialogue/dialogue-script-registry';
import { DialogueLibraryDefinition, DialogueScript, DialogueTriggerContext } from '../../dialogue/dialogue.models';
import { ApiHttpService } from '../api-http/api-http.service';

@Injectable({ providedIn: 'root' })
export class DialogueService {
  private libraryPromise: Promise<DialogueLibraryDefinition> | null = null;

  constructor(private readonly apiHttp: ApiHttpService) {}

  async getDialogue(context: DialogueTriggerContext): Promise<DialogueScript | null> {
    const library = await this.loadLibrary();
    return findDialogueScript(library, context);
  }

  async markDialogueSeen(dialogueId: string): Promise<void> {
    await this.apiHttp.postWithCsrf(`/api/v1/dialogues/${encodeURIComponent(dialogueId)}/seen`, {});
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
