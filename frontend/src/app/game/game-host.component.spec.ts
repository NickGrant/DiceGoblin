import { TestBed } from '@angular/core/testing';
import { GAME_RUNTIME_FACTORY, GameHostComponent } from './game-host.component';
import { GameRuntime } from './runtime/game-runtime';

describe('GameHostComponent', () => {
  let runtimes: jasmine.SpyObj<GameRuntime>[];
  let runtimeFactory: jasmine.Spy<() => GameRuntime>;

  beforeEach(() => {
    runtimes = [];
    runtimeFactory = jasmine.createSpy('runtimeFactory').and.callFake(() => {
      const runtime = jasmine.createSpyObj<GameRuntime>('GameRuntime', ['mount', 'destroy']);
      runtimes.push(runtime);
      return runtime;
    });

    TestBed.configureTestingModule({
      imports: [GameHostComponent],
      providers: [{ provide: GAME_RUNTIME_FACTORY, useValue: runtimeFactory }],
    });
  });

  it('creates and mounts exactly one runtime despite repeated change detection', async () => {
    await TestBed.compileComponents();
    const fixture = TestBed.createComponent(GameHostComponent);

    fixture.detectChanges();
    fixture.detectChanges();
    fixture.detectChanges();

    expect(runtimeFactory).toHaveBeenCalledTimes(1);
    expect(runtimes[0].mount).toHaveBeenCalledTimes(1);
    expect(runtimes[0].mount.calls.mostRecent().args[0]).toBe(
      fixture.nativeElement.querySelector('.game-host__mount'),
    );
  });

  it('destroys the runtime when Angular destroys the host', async () => {
    await TestBed.compileComponents();
    const fixture = TestBed.createComponent(GameHostComponent);
    fixture.detectChanges();

    fixture.destroy();

    expect(runtimes[0].destroy).toHaveBeenCalledTimes(1);
  });

  it('creates a fresh runtime when a new host is entered after leaving', async () => {
    await TestBed.compileComponents();
    const firstFixture = TestBed.createComponent(GameHostComponent);
    firstFixture.detectChanges();
    firstFixture.destroy();

    const secondFixture = TestBed.createComponent(GameHostComponent);
    secondFixture.detectChanges();

    expect(runtimeFactory).toHaveBeenCalledTimes(2);
    expect(runtimes[0].destroy).toHaveBeenCalledTimes(1);
    expect(runtimes[1].mount).toHaveBeenCalledTimes(1);
  });

  it('renders only the Phaser mount surface without prototype gameplay chrome', async () => {
    await TestBed.compileComponents();
    const fixture = TestBed.createComponent(GameHostComponent);
    fixture.detectChanges();
    const host = fixture.nativeElement as HTMLElement;

    expect(host.querySelector('.game-host__mount')).not.toBeNull();
    expect(host.querySelector('app-command-controls')).toBeNull();
    expect(host.querySelector('.status-card')).toBeNull();
    expect(host.querySelector('.orientation-gate')).toBeNull();
    expect(host.querySelector('app-page-frame')).toBeNull();
  });
});
