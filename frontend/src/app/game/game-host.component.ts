import {
  AfterViewInit,
  Component,
  ElementRef,
  InjectionToken,
  OnDestroy,
  ViewChild,
  inject,
} from '@angular/core';
import { GameRuntime } from './runtime/game-runtime';

export type GameRuntimeFactory = () => GameRuntime;

export const GAME_RUNTIME_FACTORY = new InjectionToken<GameRuntimeFactory>('GAME_RUNTIME_FACTORY', {
  providedIn: 'root',
  factory: () => () => new GameRuntime(),
});

@Component({
  selector: 'app-game-host',
  templateUrl: './game-host.component.html',
  styleUrl: './game-host.component.scss',
})
export class GameHostComponent implements AfterViewInit, OnDestroy {
  private readonly runtime = inject(GAME_RUNTIME_FACTORY)();

  @ViewChild('gameMount', { static: true })
  private readonly gameMount!: ElementRef<HTMLElement>;

  ngAfterViewInit(): void {
    this.runtime.mount(this.gameMount.nativeElement);
  }

  ngOnDestroy(): void {
    this.runtime.destroy();
  }
}
