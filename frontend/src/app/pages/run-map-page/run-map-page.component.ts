import { UpperCasePipe } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { CurrentRunData, CurrentRunNode } from '../../core/models/api.models';
import { RunService } from '../../core/services/run/run.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DgPageFrameComponent } from '../../shared/ui/dg-page-frame/dg-page-frame.component';

@Component({
  selector: 'app-run-map-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, DgPageFrameComponent, UpperCasePipe],
  templateUrl: './run-map-page.component.html',
  styleUrl: './run-map-page.component.scss',
})
export class RunMapPageComponent {
  private readonly router = inject(Router);
  private readonly runService = inject(RunService);

  readonly runData = signal<CurrentRunData | null>(null);
  readonly loading = signal(true);
  readonly working = signal(false);
  readonly error = signal<string | null>(null);

  readonly nodes = computed(() => this.runData()?.map?.nodes ?? []);
  readonly edges = computed(() => this.runData()?.map?.edges ?? []);
  readonly run = computed(() => this.runData()?.run ?? null);

  constructor() {
    void this.load();
  }

  async load(): Promise<void> {
    this.loading.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.getCurrentRun();
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.runData.set(response.data);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load current run.');
    } finally {
      this.loading.set(false);
    }
  }

  nodeX(node: CurrentRunNode): number {
    return 120 + node.node_index * 140;
  }

  nodeY(node: CurrentRunNode): number {
    const offset = node.node_index % 2 === 0 ? 90 : 190;
    return offset;
  }

  nodeById(nodeId: string): CurrentRunNode | undefined {
    return this.nodes().find((node) => node.id === nodeId);
  }

  edgeX(nodeId: string): number {
    const node = this.nodeById(nodeId);
    return node ? this.nodeX(node) : 0;
  }

  edgeY(nodeId: string): number {
    const node = this.nodeById(nodeId);
    return node ? this.nodeY(node) : 0;
  }

  async openNode(node: CurrentRunNode): Promise<void> {
    if (node.status !== 'available' || !this.run()) {
      return;
    }

    if (node.node_type === 'rest') {
      await this.router.navigate(['/run/rest', node.id]);
      return;
    }

    if (node.node_type === 'exit') {
      await this.finishRun();
      return;
    }

    await this.router.navigate(['/run/node', node.id]);
  }

  async abandonRun(): Promise<void> {
    if (!this.run()) {
      return;
    }
    this.working.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.abandonRun(this.run()!.run_id);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      await this.router.navigateByUrl('/run/summary');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to abandon run.');
    } finally {
      this.working.set(false);
    }
  }

  async finishRun(): Promise<void> {
    if (!this.run()) {
      return;
    }
    this.working.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.exitRun(this.run()!.run_id);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      await this.router.navigateByUrl('/run/summary');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to exit run.');
    } finally {
      this.working.set(false);
    }
  }
}

