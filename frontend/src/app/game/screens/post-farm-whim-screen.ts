import Phaser from 'phaser';
import { PostFarmWhimFlow, PostFarmWhimView } from '../runtime/post-farm-whim-flow';
import { Bounds, RuntimeViewport, RuntimeViewportSnapshot } from '../runtime/runtime-viewport';
import { GameSceneScreen } from './game-screen-navigation';
import { createOnboardingLayout } from './onboarding-screen';
import { progressionBackground, progressionButton } from './progression-surface';

export class PostFarmWhimScreen implements GameSceneScreen {
  readonly key = 'whim' as const;
  private root: Phaser.GameObjects.Container | null = null;
  private unsubscribe: (() => void) | null = null;
  private routed = false;

  constructor(
    private readonly scene: Phaser.Scene,
    private readonly viewport: RuntimeViewport,
    private readonly flow: PostFarmWhimFlow,
    private readonly returnToCamp: () => void,
  ) {}

  create(): void {
    this.unsubscribe = this.flow.subscribe((view) => {
      if (view.kind === 'complete' && !this.routed) {
        this.routed = true;
        this.returnToCamp();
      } else if (!this.routed) this.render(view, this.viewport.snapshot);
    });
    void this.flow.refresh();
  }

  reflow(snapshot: RuntimeViewportSnapshot): void { this.render(this.flow.view, snapshot); }

  requestBack(): void {
    if (this.flow.view.kind !== 'submitting') this.returnToCamp();
  }

  destroy(): void {
    this.unsubscribe?.();
    this.unsubscribe = null;
    this.flow.dispose();
    this.root?.destroy(true);
    this.root = null;
  }

  private render(view: PostFarmWhimView, snapshot: RuntimeViewportSnapshot): void {
    if (this.routed || view.kind === 'complete') return;
    this.root?.destroy(true);
    const root = this.scene.add.container(0, 0).setScale(snapshot.gameScale);
    this.root = root;
    const layout = createOnboardingLayout(snapshot);
    progressionBackground(this.scene, root, snapshot, layout.panel);
    const heading = view.kind === 'dialogue' || view.kind === 'retryable' || view.kind === 'submitting'
      ? view.dialogue.title : 'The Whim';
    const body = view.kind === 'dialogue' || view.kind === 'retryable' ? view.dialogue.body
      : view.kind === 'submitting' ? 'The Whim waits for your answer.'
        : view.kind === 'loading' ? 'Restoring the conversation...'
          : view.kind === 'unavailable' ? 'The Whim has nothing more to ask right now.' : view.message;
    root.add(this.scene.add.text(layout.content.x, layout.content.y, heading, {
      color: '#273e35', fontFamily: 'system-ui', fontSize: `${layout.titleSize}px`, fontStyle: 'bold',
      wordWrap: { width: layout.content.width },
    }));
    root.add(this.scene.add.text(layout.content.x, layout.content.y + 86, body, {
      color: '#3a2a1a', fontFamily: 'system-ui', fontSize: `${layout.bodySize}px`,
      lineSpacing: 8, wordWrap: { width: layout.content.width },
    }));
    if ((view.kind === 'dialogue' || view.kind === 'retryable') && view.message) {
      root.add(this.scene.add.text(layout.content.x, layout.action.y - 54, view.message, {
        color: '#7a3925', fontFamily: 'system-ui', fontSize: '20px',
        wordWrap: { width: layout.content.width },
      }));
    }
    if (view.kind === 'dialogue' || view.kind === 'retryable') {
      const choices = view.dialogue.choices;
      const gap = 14;
      const width = (layout.action.width - gap * (choices.length - 1)) / choices.length;
      choices.forEach((choice, index) => progressionButton(this.scene, root,
        box(layout.action.x + index * (width + gap), layout.action.y, width, layout.action.height),
        view.kind === 'retryable' ? `RETRY ${choice.label.toUpperCase()}` : choice.label,
        () => void this.flow.choose(choice.id)));
    } else if (view.kind === 'error' || view.kind === 'sync_error') {
      progressionButton(this.scene, root, layout.action, 'TRY AGAIN', () => void this.flow.refresh());
    }
    if (view.kind !== 'submitting') {
      progressionButton(this.scene, root,
        box(layout.panel.x + 24, layout.panel.y + 24, 130, 54), 'CAMP', () => this.requestBack());
    }
  }
}

const box = (x: number, y: number, width: number, height: number): Bounds =>
  ({ x, y, width, height, right: x + width, bottom: y + height });
