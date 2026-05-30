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
    sessionService = jasmine.createSpyObj<SessionService>('SessionService', ['refreshProfile']);
    sessionService.refreshProfile.and.resolveTo();

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
    });
    expect(sessionService.refreshProfile).toHaveBeenCalledWith({ force: true });
  });

  it('routes node resolution payloads through the api', async () => {
    const response = { ok: true } as any;
    apiHttp.postWithCsrf.and.resolveTo(response);

    await service.resolveNode('1', '2', '3');

    expect(apiHttp.postWithCsrf).toHaveBeenCalledWith('/api/v1/runs/1/nodes/2/resolve', { team_id: 3 });
  });

  it('clears summary state', () => {
    (service as any).summaryState.set({ title: 'x', status: 'y', rewards: [], progression: [], survivors: [], defeated: [] });
    service.clearSummary();
    expect(service.summary()).toBeNull();
  });
});
