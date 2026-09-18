import { RunNodeResolutionAttempt } from './run-node-resolution-attempt';
import { RuntimeApiClient, RuntimeApiError } from './runtime-api-client';

describe('RunNodeResolutionAttempt', () => {
  const success: any = { resolutionType: 'combat', battle: { id: '81' }, node: { id: '10' }, run: { id: '41' } };
  const acceptsCombat = (result: any) => result.resolutionType === 'combat'
    && result.run.id === '41' && result.node.id === '10';
  function api() { return jasmine.createSpyObj<RuntimeApiClient>('api', ['resolveRunNode']); }

  it('prevents concurrent submission and preserves one key across every ambiguous retry', async () => {
    const client = api(); const attempt = new RunNodeResolutionAttempt(() => 'run-node:fixed'); attempt.begin('41', '10');
    let reject!: (reason: unknown) => void; client.resolveRunNode.and.returnValue(new Promise((_resolve, fail) => reject = fail));
    const first = attempt.submit(client, 'csrf', acceptsCombat); const duplicate = await attempt.submit(client, 'csrf', acceptsCombat);
    expect(duplicate.kind).toBe('ignored'); expect(client.resolveRunNode).toHaveBeenCalledTimes(1); reject(new RuntimeApiError('network')); await first;
    client.resolveRunNode.and.returnValues(Promise.reject(new RuntimeApiError('malformed-response', 200)),
      Promise.reject(new RuntimeApiError('http', 503)), Promise.resolve(success));
    for (let index = 0; index < 3; index++) { attempt.begin('41', '10'); await attempt.submit(client, 'csrf', acceptsCombat); }
    expect(client.resolveRunNode.calls.allArgs().map((args) => args.slice(0, 2).concat(args[3]))).toEqual([
      ['41', '10', 'run-node:fixed'], ['41', '10', 'run-node:fixed'], ['41', '10', 'run-node:fixed'], ['41', '10', 'run-node:fixed'],
    ]);
  });

  it('preserves one attempt identity when a successful HTTP response fails action semantics', async () => {
    const client = api(); const createKey = jasmine.createSpy('createKey').and.returnValues('run-node:first', 'run-node:second');
    const attempt = new RunNodeResolutionAttempt(createKey); attempt.begin('41', '10');
    client.resolveRunNode.and.returnValues(Promise.resolve({ ...success, resolutionType: 'loot' }), Promise.resolve(success));

    expect((await attempt.submit(client, 'csrf', acceptsCombat)).kind).toBe('ambiguous');
    expect(attempt.state).toBe('retryable');
    attempt.begin('41', '10');
    expect((await attempt.submit(client, 'csrf', acceptsCombat)).kind).toBe('success');

    expect(createKey).toHaveBeenCalledTimes(1);
    expect(client.resolveRunNode.calls.allArgs().map((args) => args[3])).toEqual(['run-node:first', 'run-node:first']);
  });

  it('classifies definitive rejection and already-resolved recovery without a POST loop', async () => {
    const client = api(); const attempt = new RunNodeResolutionAttempt(() => 'k'); attempt.begin('41', '10');
    client.resolveRunNode.and.rejectWith(new RuntimeApiError('http', 422, 'combat_configuration_invalid'));
    expect((await attempt.submit(client, 'csrf', acceptsCombat)).kind).toBe('rejected');
    attempt.begin('41', '10'); client.resolveRunNode.and.rejectWith(new RuntimeApiError('http', 409, 'run_node_already_resolved'));
    expect((await attempt.submit(client, 'csrf', acceptsCombat)).kind).toBe('already-resolved'); expect(client.resolveRunNode).toHaveBeenCalledTimes(2);
  });
});
