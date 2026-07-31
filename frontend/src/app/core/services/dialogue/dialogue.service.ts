import { Injectable } from '@angular/core';
import { findDialogueScript, materializeDialogueScript } from '../../dialogue/dialogue-script-registry';
import { DialogueLibraryDefinition, DialogueScript, DialogueTriggerContext } from '../../dialogue/dialogue.models';
import { ApiHttpService } from '../api-http/api-http.service';

@Injectable({ providedIn: 'root' })
export class DialogueService {
  private readonly libraryUrls = [
    '/assets/data/dialogue/dialogue-scripts.json',
    '/assets/data/dialogue/consumable-dialogue-scripts.json',
  ];
  private libraryPromise: Promise<DialogueLibraryDefinition> | null = null;

  constructor(private readonly apiHttp: ApiHttpService) {}

  async getDialogue(context: DialogueTriggerContext): Promise<DialogueScript | null> {
    const library = await this.loadLibrary();
    return findDialogueScript(library, context);
  }

  async getDialogueById(dialogueId: string, context: DialogueTriggerContext): Promise<DialogueScript | null> {
    const library = await this.loadLibrary();
    const script = library.scripts.find((candidate) => candidate.id === dialogueId) ?? null;
    return script ? materializeDialogueScript(script, context) : null;
  }

  async getLoreDialogues(dialogueIds: ReadonlyArray<string>, context: DialogueTriggerContext): Promise<DialogueScript[]> {
    const library = await this.loadLibrary();
    const seenIds = new Set(dialogueIds);
    return library.scripts
      .filter((script) => seenIds.has(script.id) && (script.tags ?? []).includes('lore'))
      .map((script) => materializeDialogueScript(script, context));
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
    const libraries = await Promise.all(this.libraryUrls.map((url) => this.fetchSingleLibrary(url)));
    return { scripts: libraries.flatMap((library) => library.scripts) };
  }

  private async fetchSingleLibrary(url: string): Promise<DialogueLibraryDefinition> {
    const response = await fetch(url, {
      headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
      throw new Error(`Unable to load dialogue scripts (${response.status}).`);
    }

    const payload = await response.json();
    return {
      scripts: Array.isArray((payload as DialogueLibraryDefinition).scripts)
        ? (payload as DialogueLibraryDefinition).scripts
        : [],
    };
  }
}
