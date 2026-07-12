import {getOrderStatusColor, getOrderStatusLabel} from '../../src/utils/orderStatus';

describe('orderStatus', () => {
  it('maps pending_payment label', () => {
    expect(getOrderStatusLabel('pending_payment')).toBe('待支付');
  });

  it('maps timeout cancelled label', () => {
    expect(getOrderStatusLabel('cancelled', '超时未支付自动取消')).toBe(
      '已取消(超时过期)',
    );
  });

  it('returns color for paid', () => {
    expect(getOrderStatusColor('paid')).toBe('#1976d2');
  });
});
