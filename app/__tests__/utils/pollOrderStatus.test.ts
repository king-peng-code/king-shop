import {getOrder} from '../../src/api/orders';
import {pollOrderStatus} from '../../src/utils/pollOrderStatus';

jest.mock('../../src/api/orders', () => ({
  getOrder: jest.fn(),
}));

const mockedGetOrder = getOrder as jest.MockedFunction<typeof getOrder>;

beforeEach(() => {
  jest.useFakeTimers();
  mockedGetOrder.mockReset();
});

afterEach(() => {
  jest.useRealTimers();
});

it('returns paid immediately when order is paid', async () => {
  mockedGetOrder.mockResolvedValue({
    id: 1,
    order_no: 'KS001',
    total_amount: 100,
    status: 'paid',
    payment_method: 'self',
    paid_at: '2026-07-12T14:30:00+08:00',
    remark: null,
    cancelled_at: null,
    cancel_reason: null,
    created_at: '2026-07-12T14:00:00+08:00',
  });

  const promise = pollOrderStatus(1, {intervalMs: 1000, maxAttempts: 3});
  await expect(promise).resolves.toBe('paid');
  expect(mockedGetOrder).toHaveBeenCalledTimes(1);
});

it('returns timeout after maxAttempts when still pending', async () => {
  mockedGetOrder.mockResolvedValue({
    id: 1,
    order_no: 'KS001',
    total_amount: 100,
    status: 'pending_payment',
    payment_method: 'self',
    paid_at: null,
    remark: null,
    cancelled_at: null,
    cancel_reason: null,
    created_at: '2026-07-12T14:00:00+08:00',
  });

  const promise = pollOrderStatus(1, {intervalMs: 100, maxAttempts: 3});

  await jest.runAllTimersAsync();
  await expect(promise).resolves.toBe('timeout');
  expect(mockedGetOrder).toHaveBeenCalledTimes(3);
});

it('returns paid on later poll attempt', async () => {
  mockedGetOrder
    .mockResolvedValueOnce({
      id: 1,
      order_no: 'KS001',
      total_amount: 100,
      status: 'pending_payment',
      payment_method: 'self',
      paid_at: null,
      remark: null,
      cancelled_at: null,
      cancel_reason: null,
      created_at: '2026-07-12T14:00:00+08:00',
    })
    .mockResolvedValueOnce({
      id: 1,
      order_no: 'KS001',
      total_amount: 100,
      status: 'paid',
      payment_method: 'self',
      paid_at: '2026-07-12T14:30:00+08:00',
      remark: null,
      cancelled_at: null,
      cancel_reason: null,
      created_at: '2026-07-12T14:00:00+08:00',
    });

  const promise = pollOrderStatus(1, {intervalMs: 100, maxAttempts: 5});

  await jest.advanceTimersByTimeAsync(100);
  await expect(promise).resolves.toBe('paid');
  expect(mockedGetOrder).toHaveBeenCalledTimes(2);
});
