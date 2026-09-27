import { RuntimeApiError } from './runtime-api-client';
import { RetainedMutationAttempt } from './retained-mutation-attempt';

describe('RetainedMutationAttempt', () => {
  it('suppresses duplicate submissions and retains request plus key across ambiguity', async () => {
    const attempt = new RetainedMutationAttempt<{ id: string }, string>(() => 'fixed-key');
    attempt.begin('purchase:1', { id: 'offer.1' });
    let reject!: (error: unknown) => void;
    const send = jasmine.createSpy('send').and.returnValue(new Promise<string>((_resolve, fail) => { reject = fail; }));
    const first = attempt.submit(send);
    expect((await attempt.submit(send)).kind).toBe('ignored');
    reject(new RuntimeApiError('network'));
    expect((await first).kind).toBe('ambiguous');
    expect(attempt.identity).toEqual({ identity: 'purchase:1', request: { id: 'offer.1' }, key: 'fixed-key' });
    send.and.resolveTo('done');
    expect((await attempt.submit(send)).kind).toBe('success');
    expect(send.calls.allArgs()).toEqual([[{ id: 'offer.1' }, 'fixed-key'], [{ id: 'offer.1' }, 'fixed-key']]);
  });

  it('permits a new key only after a definitive rejection or completed action', async () => {
    let key = 0;
    const attempt = new RetainedMutationAttempt<{ id: string }, string>(() => `key-${++key}`);
    attempt.begin('use:1', { id: 'item.1' });
    expect((await attempt.submit(async () => { throw new RuntimeApiError('http', 422); })).kind).toBe('rejected');
    attempt.begin('use:1', { id: 'item.1' });
    expect(attempt.identity?.key).toBe('key-2');
  });
});
