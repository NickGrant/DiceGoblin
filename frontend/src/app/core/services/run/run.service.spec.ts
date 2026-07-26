import { TestBed } from '@angular/core/testing';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';
import { RunService } from './run.service';

describe('RunService', () => {
  let service: RunService;
  let apiHttp: jasmine.SpyObj<ApiHttpService>;
  let sessionService: jasmine.SpyObj<SessionService>;

  beforeEach(() => {
    apiHttp = jasmine.createSpyObj<ApiHttpService>('ApiHttpService', ['get', 'postWithCsrf', 'putWithCsrf']);
    sessionService = jasmine.createSpyObj<SessionService>('SessionService', ['runProfileMutation']);
    sessionService.runProfileMutation.and.callFake(async <T>(operation: () => Promise<T>) => {
      return operation();
    });

    TestBed.configureTestingModule({
      providers: [
        RunService,
        { provide: ApiHttpService, useValue: apiHttp },
        { provide: SessionService, useValue: sessionService },
      ],
    });

    service = TestBed.inject(RunService);
  });

  it('loads the current run', async () => {
    const response = { ok: true, data: {} } as any;
    apiHttp.get.and.resolveTo(response);

    await expectAsync(service.getCurrentRun()).toBeResolvedTo(response);
    expect(apiHttp.get).toHaveBeenCalledWith('/api/v1/runs/current');
  });

  it('parses run node meta_json into meta for the map renderer', async () => {
    apiHttp.get.and.resolveTo({
      ok: true,
      data: {
        run: { run_id: '7', region_id: '1', seed: 'abc', status: 'active', started_at: '2026-07-04', ended_at: null },
        map: {
          nodes: [
            {
              id: 'n1',
              run_id: '7',
              node_index: 0,
              node_type: 'combat',
              status: 'available',
              meta_json: '{"col":0,"row":4}',
            },
          ],
          edges: [
            {
              run_id: '7',
              from_node_id: 'n1',
              to_node_id: 'n2',
            },
          ],
        },
        run_unit_state: [],
      },
    } as any);

    const response = await service.getCurrentRun();

    expect(response.ok).toBeTrue();
    if (!response.ok) {
      fail('Expected ok response');
      return;
    }

    expect(response.data.map?.nodes[0]?.meta).toEqual({ col: 0, row: 4 });
    expect(response.data.map?.edges[0]?.edge_id).toBe('n1->n2#0');
  });

  it('captures summary state when abandoning a run succeeds', async () => {
    const response = {
      ok: true,
      data: {
        status: 'abandoned',
        run_summary: {
          rewards: ['tooth'],
          progression: ['node 3'],
          survivors: ['Gobi'],
          defeated: ['Snail'],
          reward_detail: {
            currency_soft: 0,
            units: [],
            dice: [],
          },
          progression_detail: [],
        },
      },
    } as any;
    apiHttp.postWithCsrf.and.resolveTo(response);

    await service.abandonRun('7');

    expect(service.summary()).toEqual({
      title: 'Run Abandoned',
      status: 'abandoned',
      rewards: ['tooth'],
      progression: ['node 3'],
      survivors: ['Gobi'],
      defeated: ['Snail'],
      meta: null,
      rewardDetail: {
        currency_soft: 0,
        units: [],
        dice: [],
      },
      stolenPages: [],
      progressionDetail: [],
    });
    expect(sessionService.runProfileMutation).toHaveBeenCalled();
  });

  it('routes node resolution payloads through the api', async () => {
    const response = { ok: true } as any;
    apiHttp.postWithCsrf.and.resolveTo(response);

    await service.resolveNode('1', '2', '3');

    expect(apiHttp.postWithCsrf).toHaveBeenCalledWith('/api/v1/runs/1/nodes/2/resolve', { team_id: 3 });
  });

  it('routes chaos finalization through a profile mutation', async () => {
    const response = { ok: true } as any;
    apiHttp.postWithCsrf.and.resolveTo(response);

    await service.finalizeChaosEncounter('1', '2');

    expect(apiHttp.postWithCsrf).toHaveBeenCalledWith('/api/v1/runs/1/nodes/2/chaos/finalize', {});
    expect(sessionService.runProfileMutation).toHaveBeenCalled();
  });

  it('clears summary state', () => {
    (service as any).summaryState.set({
      title: 'x',
      status: 'y',
      rewards: [],
      progression: [],
      survivors: [],
      defeated: [],
      meta: null,
      rewardDetail: null,
      stolenPages: [],
      progressionDetail: [],
    });
    service.clearSummary();
    expect(service.summary()).toBeNull();
  });
});
