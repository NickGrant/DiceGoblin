import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { DialogueScript, DialogueTriggerContext } from '../../core/dialogue/dialogue.models';
import { CurrentRunNode } from '../../core/models/api.models';
import { DialogueService } from '../../core/services/dialogue/dialogue.service';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgDialogueStageComponent } from '../../shared/ui/dg-dialogue-stage/dg-dialogue-stage.component';

@Component({
  selector: 'app-run-dialogue-page',
  standalone: true,
  imports: [DgAlertComponent, DgDialogueStageComponent, PageFrameComponent],
  templateUrl: './run-dialogue-page.component.html',
  styleUrl: './run-dialogue-page.component.scss',
})
export class RunDialoguePageComponent {
  private static readonly PLAYER_DIALOGUE_PORTRAIT =
    '/assets/dialogue/portraits/goblin/base_frame_0.png';

  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly runService = inject(RunService);
  private readonly dialogueService = inject(DialogueService);
  private readonly sessionService = inject(SessionService);

  readonly nodeId = this.route.snapshot.paramMap.get('nodeId') ?? '';
  readonly runId = signal<string | null>(null);
  readonly script = signal<DialogueScript | null>(null);
  readonly loading = signal(true);
  readonly busy = signal(false);
  readonly error = signal<string | null>(null);
  readonly pageTitle = computed(() => this.script()?.title ?? 'Run Dialogue');
  readonly pageSubtitle = computed(() => this.script()?.summary ?? '');

  constructor() {
    void this.load();
  }

  async load(): Promise<void> {
    this.loading.set(true);
    this.error.set(null);

    try {
      const current = await this.runService.getCurrentRun();
      if (!current.ok || !current.data.run) {
        this.error.set(current.ok ? 'No active run.' : current.error.message);
        return;
      }

      const node = current.data.map?.nodes.find((candidate) => candidate.id === this.nodeId) ?? null;
      if (!node) {
        this.error.set('Dialogue node not found.');
        return;
      }

      if (node.node_type !== 'dialogue') {
        await this.router.navigate(['/run/node', this.nodeId]);
        return;
      }

      if (node.status === 'locked') {
        await this.router.navigateByUrl('/run/map');
        return;
      }

      this.runId.set(current.data.run.run_id);
      const dialogueId = this.dialogueIdForNode(node);
      if (!dialogueId) {
        this.error.set('Dialogue node is missing its script id.');
        return;
      }

      const script = await this.dialogueService.getDialogueById(dialogueId, {
        scene: 'run-dialogue',
        nodeType: node.node_type,
        regionSlug: current.data.run.region_slug ?? null,
        regionId: current.data.run.region_id ?? null,
        tags: this.dialogueTagsForNode(node),
        playerName: this.sessionService.session().displayName,
        playerPortraitUrl: RunDialoguePageComponent.PLAYER_DIALOGUE_PORTRAIT,
      } satisfies DialogueTriggerContext);

      if (!script) {
        this.error.set(`Dialogue script "${dialogueId}" could not be loaded.`);
        return;
      }

      this.script.set(script);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load dialogue.');
    } finally {
      this.loading.set(false);
    }
  }

  async completeDialogue(): Promise<void> {
    const runId = this.runId();
    if (!runId || this.busy()) {
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.completeDialogueNode(runId, this.nodeId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      await this.router.navigateByUrl('/run/map');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to complete dialogue.');
    } finally {
      this.busy.set(false);
    }
  }

  private dialogueIdForNode(node: CurrentRunNode): string | null {
    const value = node.meta?.['dialogue_id'];
    return typeof value === 'string' && value.trim() ? value.trim() : null;
  }

  private dialogueTagsForNode(node: CurrentRunNode): string[] {
    const value = node.meta?.['tags'];
    if (!Array.isArray(value)) {
      return [];
    }

    return value.filter((tag): tag is string => typeof tag === 'string' && tag.trim().length > 0);
  }
}
