import { DialogueContractError, parseDialogueCompletionEnvelope, parseDialogueReadEnvelope } from './dialogue-contracts';
import { RuntimeApiClient } from './runtime-api-client';
import { RetainedMutationAttempt } from './retained-mutation-attempt';

describe('Dialogue browser contract', () => {
  const id = 'dialogue.camp.farm_scout';
  const choice = 'hear_paths';
  const read = { ok: true, data: { id, title: 'The Farm Scout', body: 'A scout points to the Farm.',
    choices: [{ id: choice, label: 'Ask about the paths' }] } };
  const completion = { ok: true, data: { dialogue_id: id, choice_id: choice,
    response: 'The scout sketches the dry route.',
    knowledge: { codex_id: 'codex.farm.old_paths', outcome: 'granted' }, player_revision: 2 } };

  it('accepts only currently authorized public read fields and stable order', () => {
    expect(parseDialogueReadEnvelope(read, id).choices.map((entry) => entry.id)).toEqual([choice]);
    expect(parseDialogueReadEnvelope({ ...read, data: { ...read.data, choices: [] } }, id).choices).toEqual([]);
    for (const value of [
      { ...read, data: { ...read.data, required_codex_ids: ['codex.farm.old_paths'] } },
      { ...read, data: { ...read.data, choices: [{ id: choice, label: 'Ask', event_id: 'event.secret' }] } },
      { ...read, data: { ...read.data, choices: [read.data.choices[0], read.data.choices[0]] } },
      { ...read, data: { ...read.data, id: 'dialogue.other' } },
    ]) expect(() => parseDialogueReadEnvelope(value, id)).toThrowError(DialogueContractError);
  });

  it('accepts finalized public knowledge only and rejects private reward facts', () => {
    expect(parseDialogueCompletionEnvelope(completion, id, choice).knowledge?.outcome).toBe('granted');
    expect(parseDialogueCompletionEnvelope({ ...completion, data: { ...completion.data,
      knowledge: null } }, id, choice).knowledge).toBeNull();
    for (const value of [
      { ...completion, data: { ...completion.data, reward_definition_id: 'reward_definition.secret' } },
      { ...completion, data: { ...completion.data, knowledge: { ...completion.data.knowledge, roll: 1 } } },
      { ...completion, data: { ...completion.data, knowledge: { codex_id: 'codex.farm.old_paths', outcome: 'not_rolled' } } },
      { ...completion, data: { ...completion.data, choice_id: 'discuss_paths' } },
    ]) expect(() => parseDialogueCompletionEnvelope(value, id, choice)).toThrowError(DialogueContractError);
  });

  it('uses authenticated API reads, CSRF mutation, and retained exact-key retry', async () => {
    const calls: { url: string; init: RequestInit }[] = [];
    let completionCalls = 0;
    const fetchRequest = jasmine.createSpy('fetch').and.callFake(async (input: RequestInfo | URL, init?: RequestInit) => {
      calls.push({ url: String(input), init: init ?? {} });
      if (init?.method === 'GET') return new Response(JSON.stringify(read), { status: 200 });
      completionCalls++;
      if (completionCalls === 1) throw new Error('ambiguous network result');
      return new Response(JSON.stringify(completion), { status: 200 });
    });
    const api = new RuntimeApiClient(fetchRequest, '/root');
    expect((await api.getDialogue(id)).choices[0].id).toBe(choice);
    const attempt = new RetainedMutationAttempt<{ dialogueId: string; choiceId: string },
      Awaited<ReturnType<RuntimeApiClient['completeDialogue']>>>(() => 'dialogue-attempt-123');
    attempt.begin(`${id}:${choice}`, { dialogueId: id, choiceId: choice });
    const send = (request: { dialogueId: string; choiceId: string }, key: string) =>
      api.completeDialogue(request.dialogueId, request.choiceId, 'csrf-token', key);
    expect((await attempt.submit(send)).kind).toBe('ambiguous');
    expect((await attempt.submit(send)).kind).toBe('success');
    expect(calls[0].url).toBe(`/root/api/v1/dialogues/${id}`);
    expect(calls[0].init.credentials).toBe('include');
    expect(calls[1].url).toBe(`/root/api/v1/dialogues/${id}/complete`);
    expect(calls[1].init.headers).toEqual(jasmine.objectContaining({
      'X-CSRF-Token': 'csrf-token', 'Idempotency-Key': 'dialogue-attempt-123',
    }));
    expect(calls[1].init.body).toBe(JSON.stringify({ choice_id: choice }));
    expect((calls[2].init.headers as Record<string, string>)['Idempotency-Key']).toBe('dialogue-attempt-123');
  });

  it('rejects private API fields before they reach the runtime', async () => {
    const privateRead = { ...read, data: { ...read.data, choices: [
      { ...read.data.choices[0], required_codex_ids: ['codex.farm.old_paths'] },
    ] } };
    const fetchRequest = jasmine.createSpy('fetch').and.resolveTo(
      new Response(JSON.stringify(privateRead), { status: 200 }));
    await expectAsync(new RuntimeApiClient(fetchRequest, '/root').getDialogue(id))
      .toBeRejectedWith(jasmine.objectContaining({ kind: 'malformed-response' }));
  });
});
