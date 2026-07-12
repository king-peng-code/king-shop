import {getOrderStatusColor, getOrderStatusLabel} from '../../src/utils/orderStatus';

describe('orderStatus', () => {
  it('maps pending_payment label', () => {
    expect(getOrderStatusLabel('pending_payment')).toBe('待支付');
  });

  it('maps ready label', () => {
    expect(getOrderStatusLabel('ready')).toBe('可取餐');
  });

  it('returns color for paid', () => {
    expect(getOrderStatusColor('paid')).toBe('#1976d2');
  });
});
