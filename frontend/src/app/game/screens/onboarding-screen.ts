import Phaser from 'phaser';
import { OnboardingFlow, OnboardingView } from '../runtime/onboarding-flow';
import { Bounds, RuntimeViewport, RuntimeViewportSnapshot } from '../runtime/runtime-viewport';
import { progressionBackground, progressionButton } from './progression-surface';

const box = (x: number, y: number, width: number, height: number): Bounds =>
  ({ x, y, width, height, right: x + width, bottom: y + height });

export interface OnboardingLayout {
  readonly panel: Bounds;
  readonly content: Bounds;
  readonly action: Bounds;
  readonly titleSize: number;
  readonly bodySize: number;
}

export function createOnboardingLayout(snapshot: RuntimeViewportSnapshot): OnboardingLayout {
  const compact = snapshot.layoutClass === 'compact';
  const safe = snapshot.safeBounds;
  const width = Math.min(compact ? 1520 : snapshot.layoutClass === 'wide' ? 1360 : 1240, safe.width - 48);
  const height = Math.min(compact ? 780 : 740, safe.height - 48);
  const panel = box(safe.x + (safe.width - width) / 2, safe.y + (safe.height - height) / 2, width, height);
  const inset = compact ? 46 : 64;
  const content = box(panel.x + inset, panel.y + (compact ? 152 : 166), panel.width - inset * 2,
    panel.height - (compact ? 280 : 300));
  const action = box(content.x, panel.bottom - (compact ? 108 : 112), content.width, compact ? 66 : 60);
  return { panel, content, action, titleSize: compact ? 34 : 42, bodySize: compact ? 26 : 29 };
}

/** Phaser-only presentation. OnboardingFlow owns authoritative reads and mutations. */
export class OnboardingScreen {
  private root: Phaser.GameObjects.Container | null = null;
  private unsubscribeFlow: (() => void) | null = null;
  private unsubscribeViewport: (() => void) | null = null;
  private routed = false;

  constructor(
    private readonly scene: Phaser.Scene,
    private readonly viewport: RuntimeViewport,
    private readonly flow: OnboardingFlow,
    private readonly enterCamp: () => void,
  ) {}

  create(): void {
    this.unsubscribeFlow = this.flow.subscribe((view) => {
      if (view.kind === 'complete' && !this.routed) {
        this.routed = true;
        this.enterCamp();
      } else if (!this.routed) this.render(view, this.viewport.snapshot);
    });
    this.unsubscribeViewport = this.viewport.subscribe((snapshot) => this.render(this.flow.view, snapshot));
  }

  destroy(): void {
    this.unsubscribeFlow?.();
    this.unsubscribeViewport?.();
    this.unsubscribeFlow = null;
    this.unsubscribeViewport = null;
    this.root?.destroy(true);
    this.root = null;
  }

  private render(view: OnboardingView, snapshot: RuntimeViewportSnapshot): void {
    if (this.routed || view.kind === 'complete') return;
    this.root?.destroy(true);
    const root = this.scene.add.container(0, 0);
    this.root = root;
    root.setScale(snapshot.gameScale);
    const layout = createOnboardingLayout(snapshot);
    progressionBackground(this.scene, root, snapshot, layout.panel);
    const graphics = this.scene.add.graphics();
    graphics.fillStyle(0x2d4540, 1);
    graphics.fillRoundedRect(layout.panel.x, layout.panel.y, layout.panel.width, 126, 16);
    graphics.fillRect(layout.panel.x, layout.panel.y + 104, layout.panel.width, 22);
    root.add(graphics);
    root.add(this.scene.add.text(layout.panel.x + layout.panel.width / 2, layout.panel.y + 33,
      'MYSTIC CAVE', { color: '#e8d9ae', fontFamily: 'system-ui', fontSize: '24px', fontStyle: 'bold' }).setOrigin(.5, 0));
    const heading = view.kind === 'dialogue' ? view.dialogue.title
      : view.kind === 'provision' ? 'Establish Your Warband'
        : view.kind === 'submitting' ? 'One moment…' : view.kind === 'error' ? 'The cave is quiet' : 'Finding Whim…';
    root.add(this.scene.add.text(layout.content.x, layout.content.y, heading, {
      color: '#273e35', fontFamily: 'system-ui', fontSize: `${layout.titleSize}px`, fontStyle: 'bold',
      wordWrap: { width: layout.content.width },
    }));
    const body = view.kind === 'dialogue' ? view.dialogue.body
      : view.kind === 'provision'
        ? 'Whim has told you what you need to know. Gather your first Basic Goblin Warband to begin at Camp.'
        : view.kind === 'submitting' ? 'Whim is waiting for the cave to answer.'
          : view.kind === 'error' ? view.message : 'Your place in the story is being restored.';
    root.add(this.scene.add.text(layout.content.x, layout.content.y + 86, body, {
      color: '#3a2a1a', fontFamily: 'system-ui', fontSize: `${layout.bodySize}px`,
      lineSpacing: 8, wordWrap: { width: layout.content.width },
    }));
    const message = view.kind === 'dialogue' || view.kind === 'provision' ? view.message : null;
    if (message) root.add(this.scene.add.text(layout.content.x, layout.action.y - 50, message, {
      color: '#7a3925', fontFamily: 'system-ui', fontSize: '20px',
      wordWrap: { width: layout.content.width },
    }));
    if (view.kind === 'dialogue') {
      const choices = view.dialogue.choices;
      const gap = 14;
      const width = (layout.action.width - gap * (choices.length - 1)) / Math.max(1, choices.length);
      choices.forEach((choice, index) => progressionButton(this.scene, root,
        box(layout.action.x + index * (width + gap), layout.action.y, width, layout.action.height),
        choice.label, () => void this.flow.choose(choice.id)));
    } else if (view.kind === 'provision') {
      progressionButton(this.scene, root, layout.action, 'ESTABLISH WARBAND', () => void this.flow.provision());
    } else if (view.kind === 'error') {
      progressionButton(this.scene, root, layout.action, 'TRY AGAIN', () => void this.flow.refresh());
    }
  }
}
