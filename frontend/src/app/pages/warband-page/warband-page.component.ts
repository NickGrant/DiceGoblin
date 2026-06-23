import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SessionService } from '../../core/services/session/session.service';
import { SquadService } from '../../core/services/squad/squad.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { ObjectGridComponent } from '../../shared/ui/object-grid/object-grid.component';
import { UnitGridObjectComponent } from '../../shared/ui/unit-grid-object/unit-grid-object.component';

@Component({
  selector: 'app-warband-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, PageFrameComponent, ObjectGridComponent, RouterLink],
  templateUrl: './warband-page.component.html',
  styleUrl: './warband-page.component.scss',
})
export class WarbandPageComponent {
  private readonly sessionService = inject(SessionService);
  private readonly squadService = inject(SquadService);

  readonly profile = this.sessionService.profile;
  readonly squads = this.sessionService.squads;
  readonly units = this.sessionService.units;
  readonly activeRun = computed(() => this.sessionService.profileData()?.active_run ?? null);
  readonly activeSquad = this.sessionService.activeSquad;
  readonly isSaving = signal(false);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly unitObjectComponent = UnitGridObjectComponent;

  isSquadLocked(teamId: string): boolean {
    return !!this.activeRun() && this.activeSquad()?.id === teamId;
  }

  squadLockMessage(teamId: string): string | null {
    return this.isSquadLocked(teamId) ? 'Locked while this squad is committed to the active run.' : null;
  }

  async createSquad(): Promise<void> {
    this.error.set(null);
    this.message.set(null);
    this.isSaving.set(true);

    try {
      const response = await this.squadService.createTeam(`New Squad ${this.squads().length + 1}`, false);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set('Squad created.');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to create squad.');
    } finally {
      this.isSaving.set(false);
    }
  }

  async activateSquad(teamId: string): Promise<void> {
    if (this.activeRun()) {
      return;
    }

    this.error.set(null);
    this.message.set(null);
    this.isSaving.set(true);

    try {
      const response = await this.squadService.activateTeam(teamId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set('Active squad updated.');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to activate squad.');
    } finally {
      this.isSaving.set(false);
    }
  }
}

