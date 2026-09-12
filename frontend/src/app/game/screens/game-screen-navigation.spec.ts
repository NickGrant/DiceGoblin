import { GameScreenNavigator } from './game-screen-navigation';

describe('GameScreenNavigator', () => {
  it('tracks Camp to Warband history and returns without browser routing', () => {
    const navigation = new GameScreenNavigator();
    expect(navigation.start('camp')).toBe('camp');
    expect(navigation.navigate('warband')).toBe('warband');
    expect(navigation.canGoBack).toBeTrue();
    expect(navigation.back()).toBe('camp');
    expect(navigation.current).toBe('camp');
    expect(navigation.canGoBack).toBeFalse();
  });
});
