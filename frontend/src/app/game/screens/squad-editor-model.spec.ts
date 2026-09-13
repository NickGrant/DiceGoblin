import { CreateSquadIdempotency, SquadEditorDraft } from './squad-editor-model';

describe('SquadEditorDraft', () => {
  it('clones committed state and keeps all local editing isolated', () => {
    const committed = { id: '31', name: 'Raiders', isActive: true, formation: ['11', null, '12', null, null, null, null, null, null] } as const;
    const draft = SquadEditorDraft.edit(committed);
    draft.setName('New Raiders');
    draft.selectUnit('11');
    draft.placeSelected(4);
    expect(draft.formation[0]).toBeNull();
    expect(draft.formation[4]).toBe('11');
    expect(committed.name).toBe('Raiders');
    expect(committed.formation[0]).toBe('11');
    expect(draft.dirty).toBeTrue();
  });

  it('enforces one unit per position and one position per unit while allowing removal and an empty formation', () => {
    const draft = SquadEditorDraft.create();
    draft.setName('Empty is okay');
    draft.selectUnit('11');
    draft.placeSelected(0);
    draft.placeSelected(8);
    expect(draft.formation.filter((id) => id === '11').length).toBe(1);
    draft.selectUnit('12');
    draft.placeSelected(8);
    expect(draft.formation[8]).toBe('12');
    draft.clearPosition(8);
    expect(draft.formation.every((id) => id === null)).toBeTrue();
    expect(draft.validationError).toBeNull();
  });

  it('validates normalized Unicode names against the 128-character contract', () => {
    const draft = SquadEditorDraft.create();
    expect(draft.validationError).toContain('name');
    draft.setName(` ${'\u{1F479}'.repeat(128)} `);
    expect(draft.validationError).toBeNull();
    draft.setName('\u{1F479}'.repeat(129));
    expect(draft.validationError).toContain('128');
  });
});

describe('CreateSquadIdempotency', () => {
  it('reuses a key for an ambiguous exact retry and rotates it after the payload changes', () => {
    let index = 0;
    const keys = new CreateSquadIdempotency(() => `squad:create:key-${++index}`);
    const first = { name: 'One', formation: Array<string | null>(9).fill(null) };
    const key = keys.keyFor(first);
    keys.recordFailure('network');
    expect(keys.keyFor(first)).toBe(key);
    expect(keys.keyFor({ ...first, name: 'Two' })).not.toBe(key);
  });

  it('abandons a key after a definitive failure or success', () => {
    let index = 0;
    const keys = new CreateSquadIdempotency(() => `squad:create:key-${++index}`);
    const payload = { name: 'One', formation: Array<string | null>(9).fill(null) };
    const first = keys.keyFor(payload);
    keys.recordFailure('http');
    expect(keys.keyFor(payload)).not.toBe(first);
    const second = keys.keyFor(payload);
    keys.clear();
    expect(keys.keyFor(payload)).not.toBe(second);
  });
});
