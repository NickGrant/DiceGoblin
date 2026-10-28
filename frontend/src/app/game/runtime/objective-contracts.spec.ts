import { ObjectiveContractError, parseObjectivesEnvelope } from './objective-contracts';

describe('Objective browser contract', () => {
  const requirement = { id: 'kobolds', label: 'Defeat kobolds', current: 0, needed: 3 };
  const objective = { id: 'objective.test.scout', title: 'Scout', description: 'Explore the mountains.',
    category: 'bounty', status: 'unstarted', requirements: [requirement] };
  const response = (entry: unknown) => ({ ok: true, data: { objectives: [entry] } });

  it('accepts the narrow unstarted, active, and completed read states', () => {
    expect(parseObjectivesEnvelope(response(objective))[0].requirements[0].current).toBe(0);
    expect(parseObjectivesEnvelope(response({ ...objective, status: 'active',
      requirements: [{ ...requirement, current: 2 }] }))[0].status).toBe('active');
    expect(parseObjectivesEnvelope(response({ ...objective, status: 'completed',
      requirements: [{ ...requirement, current: 3 }] }))[0].status).toBe('completed');
    expect(parseObjectivesEnvelope(response({ ...objective, status: 'active',
      requirements: [{ ...requirement, current: 3 }] }))[0].status).toBe('active');
    expect(parseObjectivesEnvelope({ ok: true, data: { objectives: [] } })).toEqual([]);
  });

  it('rejects private routing, extra fields, duplicate identities, and incoherent progress', () => {
    for (const value of [
      response({ ...objective, completion_event_id: 'event.hidden' }),
      response({ ...objective, requirements: [{ ...requirement, target_id: 'enemy_unit_type.hidden' }] }),
      response({ ...objective, requirements: [requirement, requirement] }),
      response({ ...objective, requirements: [{ ...requirement, current: 4 }] }),
      response({ ...objective, status: 'completed' }),
      response({ ...objective, status: 'unstarted', requirements: [{ ...requirement, current: 1 }] }),
      { ok: true, data: { objectives: [objective, objective] } },
      { ok: true, data: { objectives: [objective], reward: { teeth: 10 } } },
    ]) expect(() => parseObjectivesEnvelope(value)).toThrowError(ObjectiveContractError);
  });
});
