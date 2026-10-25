import { CodexContractError, parseCodexEnvelope } from './codex-contracts';

describe('Codex browser contract', () => {
  const response = (entry: unknown) => ({ ok: true, data: { entries: [entry] } });
  const owned = { id: 'codex.farm.mudking', title: 'The Mudking', body: 'The Mudking rules the Farm.', owned: true };

  it('accepts owned knowledge and redacted unowned catalog entries', () => {
    expect(parseCodexEnvelope(response(owned))[0].body).toBe(owned.body);
    expect(parseCodexEnvelope(response({ ...owned, body: null, owned: false }))[0].body).toBeNull();
  });

  it('rejects private fields, leaked undiscovered body, duplicate IDs, and malformed envelopes', () => {
    for (const value of [
      response({ ...owned, reward_probability: 10000 }),
      response({ ...owned, owned: false }),
      { ok: true, data: { entries: [owned, owned] } },
      { ok: true, data: { entries: [owned], event_id: 'event.hidden' } },
      response({ ...owned, id: 'unlock.farm.mudking' }),
    ]) expect(() => parseCodexEnvelope(value)).toThrowError(CodexContractError);
  });
});
