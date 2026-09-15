import { RuntimeApiErrorKind } from '../runtime/runtime-api-client';
import { SquadConfigurationPayload, WarbandSquadSummary } from '../runtime/warband-contracts';

export type SquadEditorMode = 'create' | 'edit';

function emptyFormation(): (string | null)[] {
  return Array.from({ length: 9 }, () => null);
}

export class SquadEditorDraft {
  readonly mode: SquadEditorMode;
  readonly squadId: string | null;
  readonly isActive: boolean;
  private readonly originalName: string;
  private readonly originalFormation: readonly (string | null)[];
  private currentName: string;
  private currentFormation: (string | null)[];
  selectedUnitId: string | null = null;

  private constructor(squad: WarbandSquadSummary | null) {
    this.mode = squad ? 'edit' : 'create';
    this.squadId = squad?.id ?? null;
    this.isActive = squad?.isActive ?? false;
    this.originalName = squad?.name ?? '';
    this.originalFormation = Object.freeze([...(squad?.formation ?? emptyFormation())]);
    this.currentName = this.originalName;
    this.currentFormation = [...this.originalFormation];
  }

  static create(): SquadEditorDraft {
    return new SquadEditorDraft(null);
  }

  static edit(squad: WarbandSquadSummary): SquadEditorDraft {
    return new SquadEditorDraft(squad);
  }

  get name(): string {
    return this.currentName;
  }

  get formation(): readonly (string | null)[] {
    return this.currentFormation;
  }

  get dirty(): boolean {
    return this.currentName !== this.originalName
      || this.formationDirty;
  }

  get formationDirty(): boolean {
    return this.currentFormation.some((unitId, index) => unitId !== this.originalFormation[index]);
  }

  get validationError(): string | null {
    const length = Array.from(this.currentName.trim()).length;
    if (length === 0) return 'Enter a squad name.';
    if (length > 128) return 'Squad names may contain at most 128 characters.';
    return null;
  }

  setName(name: string): void {
    this.currentName = name;
  }

  selectUnit(unitId: string | null): void {
    this.selectedUnitId = unitId;
  }

  placeSelected(position: number): void {
    if (!Number.isInteger(position) || position < 0 || position > 8 || this.selectedUnitId === null) return;
    this.currentFormation = this.currentFormation.map((unitId) => unitId === this.selectedUnitId ? null : unitId);
    this.currentFormation[position] = this.selectedUnitId;
  }

  clearPosition(position: number): void {
    if (!Number.isInteger(position) || position < 0 || position > 8) return;
    this.currentFormation[position] = null;
  }

  payload(): SquadConfigurationPayload {
    return Object.freeze({ name: this.currentName.trim(), formation: Object.freeze([...this.currentFormation]) });
  }
}

export type IdempotencyKeyFactory = () => string;

export class CreateSquadIdempotency {
  private payloadFingerprint: string | null = null;
  private key: string | null = null;

  constructor(private readonly createKey: IdempotencyKeyFactory = defaultIdempotencyKey) {}

  keyFor(payload: SquadConfigurationPayload): string {
    const fingerprint = JSON.stringify(payload);
    if (this.payloadFingerprint !== fingerprint || this.key === null) {
      this.payloadFingerprint = fingerprint;
      this.key = this.createKey();
    }
    return this.key;
  }

  recordFailure(kind: RuntimeApiErrorKind): void {
    if (kind !== 'network' && kind !== 'malformed-response') this.clear();
  }

  clear(): void {
    this.payloadFingerprint = null;
    this.key = null;
  }
}

function defaultIdempotencyKey(): string {
  const uuid = globalThis.crypto?.randomUUID?.();
  if (uuid) return `squad:create:${uuid}`;
  const random = Math.random().toString(36).slice(2);
  return `squad:create:${Date.now().toString(36)}:${random}`;
}
