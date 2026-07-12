import {formatPrice} from '../../src/utils/formatPrice';

describe('formatPrice', () => {
  it('formats cents to yuan with two decimals', () => {
    expect(formatPrice(1500)).toBe('¥15.00');
  });

  it('formats zero', () => {
    expect(formatPrice(0)).toBe('¥0.00');
  });

  it('formats fractional yuan cents', () => {
    expect(formatPrice(99)).toBe('¥0.99');
  });
});
