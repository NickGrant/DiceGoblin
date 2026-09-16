import { BattleMarkerStorage, BattlePresentationState } from './battle-presentation';

describe('BattlePresentationState', () => {
  function storage(): jasmine.SpyObj<BattleMarkerStorage> & { value: string | null } {
    const result = jasmine.createSpyObj<BattleMarkerStorage>('storage', ['getItem', 'setItem', 'removeItem']) as jasmine.SpyObj<BattleMarkerStorage> & { value: string | null };
    result.value = null; result.getItem.and.callFake(() => result.value); result.setItem.and.callFake((_key, value) => result.value = value);
    result.removeItem.and.callFake(() => result.value = null); return result;
  }

  it('stores only scoped battle/run/node identity and restores it for the same account', () => {
    const backing = storage(); const first = new BattlePresentationState(backing);
    first.establish('1', '81', '41', '10');
    expect(JSON.parse(backing.value!)).toEqual({ version: 1, accountId: '1', battleId: '81', runId: '41', runNodeId: '10' });
    expect(new BattlePresentationState(backing).restoreForAccount('1')).toEqual(first.marker);
  });

  it('clears foreign, malformed, and extra marker state without exposing a target', () => {
    const backing = storage(); backing.value = JSON.stringify({ version: 1, accountId: '2', battleId: '81', runId: '41', runNodeId: '10' });
    expect(new BattlePresentationState(backing).restoreForAccount('1')).toBeNull(); expect(backing.removeItem).toHaveBeenCalled();
    backing.value = '{bad'; expect(new BattlePresentationState(backing).restoreForAccount('1')).toBeNull();
    backing.value = JSON.stringify({ version: 1, accountId: '1', battleId: '81', runId: '41', runNodeId: '10', events: [] });
    expect(new BattlePresentationState(backing).restoreForAccount('1')).toBeNull();
  });
});
