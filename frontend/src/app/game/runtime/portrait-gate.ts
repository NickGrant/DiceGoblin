import { RuntimeViewportSnapshot } from './runtime-viewport';

/** Runtime-owned DOM overlay that blocks all gameplay input without replacing Phaser state. */
export class PortraitGate {
  readonly element: HTMLDivElement;

  constructor(parent: HTMLElement) {
    const element = document.createElement('div');
    element.dataset['gamePortraitGate'] = 'inactive';
    element.setAttribute('role', 'status');
    element.setAttribute('aria-live', 'polite');
    Object.assign(element.style, {
      position: 'absolute',
      inset: '0',
      zIndex: '20',
      display: 'none',
      alignItems: 'center',
      justifyContent: 'center',
      boxSizing: 'border-box',
      padding:
        'max(24px, env(safe-area-inset-top)) max(24px, env(safe-area-inset-right)) max(24px, env(safe-area-inset-bottom)) max(24px, env(safe-area-inset-left))',
      color: '#f5e8c8',
      background:
        'radial-gradient(circle at 50% 35%, rgba(92,143,216,.22), transparent 42%), linear-gradient(145deg, #10282a 0%, #081b1d 55%, #3a2417 100%)',
      textAlign: 'center',
      touchAction: 'none',
      pointerEvents: 'auto',
    });

    const card = document.createElement('div');
    Object.assign(card.style, {
      width: 'min(420px, 88vw)',
      boxSizing: 'border-box',
      padding: '28px 30px 30px',
      border: '4px solid #c9972b',
      borderRadius: '24px',
      background: 'linear-gradient(180deg, rgba(138,90,52,.98), rgba(58,36,23,.98))',
      boxShadow: '0 14px 0 rgba(20,12,8,.5), 0 22px 50px rgba(0,0,0,.45)',
    });
    const icon = document.createElement('div');
    icon.textContent = '↻';
    Object.assign(icon.style, {
      width: '76px',
      height: '76px',
      margin: '0 auto 14px',
      border: '4px solid #f2c14e',
      borderRadius: '22px',
      font: '700 56px/68px Georgia, serif',
      color: '#f2c14e',
      transform: 'rotate(-18deg)',
      background: '#3a2a1a',
    });
    const heading = document.createElement('h1');
    heading.textContent = 'TURN THE TROUBLE SIDEWAYS';
    Object.assign(heading.style, {
      margin: '0 0 10px',
      color: '#fff4d3',
      font: '700 25px/1.1 Georgia, serif',
      textShadow: '0 3px 0 #3a2a1a',
    });
    const detail = document.createElement('p');
    detail.textContent = 'Rotate your device to landscape to return to Camp.';
    Object.assign(detail.style, {
      margin: '0',
      color: '#f5e8c8',
      font: '700 16px/1.4 system-ui, sans-serif',
    });
    card.append(icon, heading, detail);
    element.append(card);
    parent.appendChild(element);
    this.element = element;
  }

  update(snapshot: RuntimeViewportSnapshot): void {
    const active = snapshot.portraitGateActive;
    this.element.dataset['gamePortraitGate'] = active ? 'active' : 'inactive';
    this.element.style.display = active ? 'flex' : 'none';
    this.element.setAttribute('aria-hidden', active ? 'false' : 'true');
  }

  destroy(): void {
    this.element.remove();
  }
}
