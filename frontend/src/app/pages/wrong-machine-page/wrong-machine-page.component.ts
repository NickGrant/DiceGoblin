import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  WrongMachineReconstructData,
  WrongMachineCostItem,
  WrongMachineReconstructionOption,
} from '../../core/models/api.models';
import { WrongMachineService } from '../../core/services/wrong-machine/wrong-machine.service';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';

@Component({
  selector: 'app-wrong-machine-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, PageFrameComponent, RouterLink],
  templateUrl: './wrong-machine-page.component.html',
  styleUrl: './wrong-machine-page.component.scss',
})
export class WrongMachinePageComponent {
  private readonly wrongMachineService = inject(WrongMachineService);

  readonly loading = signal(true);
  readonly busyLineageSlug = signal<string | null>(null);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly reconstructions = signal<WrongMachineReconstructionOption[]>([]);
  readonly lastReconstruction = signal<WrongMachineReconstructData | null>(null);
  readonly featureUnlocked = signal(false);
  readonly pigKin = computed(() => this.reconstructions()[0] ?? null);
  readonly rawChaosLabel = computed(() => {
    const rawChaos = this.pigKin()?.cost.raw_chaos;
    return rawChaos ? `${rawChaos.quantity_owned}/${rawChaos.quantity_required}` : '0/0';
  });
  readonly stageSummary = computed(() => {
    const option = this.pigKin();
    if (!option) {
      return 'No reconstruction recipe is currently loaded.';
    }

    if (option.is_unlocked) {
      return `${option.name} is already restored. The lineage is available in your warband.`;
    }

    if (option.can_reconstruct) {
      return `${option.name} is ready. Confirm reconstruction to unlock the lineage and grant a new unit.`;
    }

    return `Recover ${this.missingLabel(option)} to complete the first reconstruction.`;
  });

  constructor() {
    void this.load();
  }

  async load(): Promise<void> {
    this.loading.set(true);
    this.error.set(null);
    try {
      const response = await this.wrongMachineService.getReconstructions();
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.featureUnlocked.set(response.data.feature_unlocked);
      this.reconstructions.set(response.data.reconstructions);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load the Wrong Machine.');
    } finally {
      this.loading.set(false);
    }
  }

  async reconstruct(option: WrongMachineReconstructionOption): Promise<void> {
    if (!option.can_reconstruct || option.is_unlocked) {
      return;
    }

    this.busyLineageSlug.set(option.lineage_slug);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.wrongMachineService.reconstruct(option.lineage_slug);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.lastReconstruction.set(response.data);
      this.message.set(this.reconstructionMessage(response.data));
      await this.load();
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to complete reconstruction.');
    } finally {
      this.busyLineageSlug.set(null);
    }
  }

  reconstructButtonLabel(option: WrongMachineReconstructionOption): string {
    if (this.busyLineageSlug() === option.lineage_slug) {
      return 'Reconstructing...';
    }

    if (option.is_unlocked) {
      return 'Unlocked';
    }

    if (!option.can_reconstruct) {
      return 'Missing Materials';
    }

    return 'Reconstruct Kin';
  }

  missingLabel(option: WrongMachineReconstructionOption): string {
    if (!option.missing.length) {
      return 'All reconstruction requirements are ready.';
    }

    return option.missing.map((missing) => {
      if (missing.type === 'raw_chaos') {
        return `${missing.quantity_missing} Raw Chaos`;
      }

      return `${missing.quantity_missing} ${this.itemLabel(missing.item_slug)}`;
    }).join(', ');
  }

  rawChaosProgress(option: WrongMachineReconstructionOption): number {
    return this.progressPercent(option.cost.raw_chaos.quantity_owned, option.cost.raw_chaos.quantity_required);
  }

  itemProgress(item: WrongMachineCostItem): number {
    return this.progressPercent(item.quantity_owned, item.quantity_required);
  }

  requirementLabel(isMet: boolean): string {
    return isMet ? 'Ready' : 'Needed';
  }

  grantLabel(option: WrongMachineReconstructionOption): string {
    const count = Math.max(0, option.grants.unit_count);
    const unitCopy = count === 1 ? 'unit' : 'units';
    return `${count} ${option.name} ${unitCopy}`;
  }

  itemLabel(itemSlug: string): string {
    return itemSlug
      .split('_')
      .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ');
  }

  private progressPercent(current: number, target: number): number {
    if (target <= 0) {
      return 100;
    }

    return Math.min(100, Math.max(0, Math.round((current / target) * 100)));
  }

  private reconstructionMessage(data: WrongMachineReconstructData): string {
    if (!data.newly_reconstructed) {
      return `${data.lineage?.name ?? 'That kin'} is already unlocked.`;
    }

    return `${data.lineage?.name ?? 'Kin'} reconstructed. A new goblin has joined the warband.`;
  }
}
