import { CombatResolutionAttempt } from './combat-resolution-attempt';
import { RuntimeApiClient, RuntimeApiError } from './runtime-api-client';

describe('CombatResolutionAttempt', () => {
  const success: any = { battle: { id: '81' }, node: { id: '10' }, run: { id: '41' } };
  function api() { return jasmine.createSpyObj<RuntimeApiClient>('api', ['resolveRunNode']); }

  it('prevents concurrent submission and preserves one key across every ambiguous retry', async () => {
    const client = api(); const attempt = new CombatResolutionAttempt(() => 'combat-node:fixed'); attempt.begin('41', '10');
    let reject!: (reason: unknown) => void; client.resolveRunNode.and.returnValue(new Promise((_resolve, fail) => reject = fail));
    const first = attempt.submit(client, 'csrf'); const duplicate = await attempt.submit(client, 'csrf');
    expect(duplicate.kind).toBe('ignored'); expect(client.resolveRunNode).toHaveBeenCalledTimes(1); reject(new RuntimeApiError('network')); await first;
    client.resolveRunNode.and.returnValues(Promise.reject(new RuntimeApiError('malformed-response', 200)),
      Promise.reject(new RuntimeApiError('http', 503)), Promise.resolve(success));
    for (let index = 0; index < 3; index++) { attempt.begin('41', '10'); await attempt.submit(client, 'csrf'); }
    expect(client.resolveRunNode.calls.allArgs().map((args) => args.slice(0, 2).concat(args[3]))).toEqual([
      ['41', '10', 'combat-node:fixed'], ['41', '10', 'combat-node:fixed'], ['41', '10', 'combat-node:fixed'], ['41', '10', 'combat-node:fixed'],
    ]);
  });

  it('classifies definitive rejection and already-resolved recovery without a POST loop', async () => {
    const client = api(); const attempt = new CombatResolutionAttempt(() => 'k'); attempt.begin('41', '10');
    client.resolveRunNode.and.rejectWith(new RuntimeApiError('http', 422, 'combat_configuration_invalid'));
    expect((await attempt.submit(client, 'csrf')).kind).toBe('rejected');
    attempt.begin('41', '10'); client.resolveRunNode.and.rejectWith(new RuntimeApiError('http', 409, 'run_node_already_resolved'));
    expect((await attempt.submit(client, 'csrf')).kind).toBe('already-resolved'); expect(client.resolveRunNode).toHaveBeenCalledTimes(2);
  });
});
