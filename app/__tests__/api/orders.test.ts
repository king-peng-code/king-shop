import {
  cancelOrder,
  completeOrder,
  createOrder,
  generateProxyPayLink,
  getOrder,
  listOrders,
  payOrder,
  simulateFakeNotify,
} from '../../src/api/orders';
import {setTokenGetter} from '../../src/api/client';

global.fetch = jest.fn();

beforeEach(() => {
  (fetch as jest.Mock).mockReset();
  setTokenGetter(() => 'tok');
});

const sampleOrder = {
  id: 10,
  order_no: 'KS202607121430001',
  total_amount: 3000,
  status: 'pending_payment',
  payment_method: 'self',
  paid_at: null,
  remark: null,
  cancelled_at: null,
  cancel_reason: null,
  created_at: '2026-07-12T14:30:00+08:00',
};

it('createOrder posts items and payment_method', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 201,
    json: async () => ({code: 0, message: 'ok', data: sampleOrder}),
  });

  const payload = {
    items: [{product_id: 1, quantity: 2}],
    payment_method: 'self' as const,
    remark: '少糖',
  };
  const result = await createOrder(payload);

  expect(result).toEqual(sampleOrder);
  expect(fetch).toHaveBeenCalledWith(
    expect.stringMatching(/\/orders$/),
    expect.objectContaining({
      method: 'POST',
      body: JSON.stringify(payload),
      headers: expect.objectContaining({
        Authorization: 'Bearer tok',
      }),
    }),
  );
});

it('getOrder fetches order by id', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({code: 0, message: 'ok', data: sampleOrder}),
  });

  const result = await getOrder(10);
  expect(result).toEqual(sampleOrder);
  expect(fetch).toHaveBeenCalledWith(
    expect.stringMatching(/\/orders\/10$/),
    expect.any(Object),
  );
});

it('payOrder posts channel to pay endpoint', async () => {
  const payResult = {
    payment: {
      id: 1,
      order_id: 10,
      out_trade_no: 'KS202607121430001P001',
      amount: 3000,
      channel: 'fake',
      status: 'pending',
    },
    pay_params: {
      channel: 'fake',
      out_trade_no: 'KS202607121430001P001',
      amount: 3000,
      order_no: 'KS202607121430001',
    },
  };
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({code: 0, message: 'ok', data: payResult}),
  });

  const result = await payOrder(10, 'fake');
  expect(result).toEqual(payResult);
  expect(fetch).toHaveBeenCalledWith(
    expect.stringMatching(/\/orders\/10\/pay$/),
    expect.objectContaining({
      method: 'POST',
      body: JSON.stringify({channel: 'fake'}),
    }),
  );
});

it('generateProxyPayLink posts to proxy-pay-link endpoint', async () => {
  const link = {
    url: 'http://localhost:5173/proxy-pay/abc123',
    token: 'abc123',
    expires_at: '2026-07-12T15:00:00+08:00',
  };
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({code: 0, message: 'ok', data: link}),
  });

  const result = await generateProxyPayLink(10);
  expect(result).toEqual(link);
  expect(fetch).toHaveBeenCalledWith(
    expect.stringMatching(/\/orders\/10\/proxy-pay-link$/),
    expect.objectContaining({method: 'POST'}),
  );
});

it('simulateFakeNotify posts TRADE_SUCCESS to alipay notify', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
  });

  await simulateFakeNotify('KS202607121430001P001');

  expect(fetch).toHaveBeenCalledWith(
    expect.stringMatching(/\/payments\/notify\/alipay$/),
    expect.objectContaining({
      method: 'POST',
      body: expect.stringContaining('TRADE_SUCCESS'),
    }),
  );
  const body = JSON.parse(
    (fetch as jest.Mock).mock.calls[0][1].body as string,
  );
  expect(body.out_trade_no).toBe('KS202607121430001P001');
});

it('listOrders passes status query', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({
      code: 0,
      message: 'ok',
      data: {items: [sampleOrder], meta: {total: 1, page: 1, per_page: 20}},
    }),
  });

  const result = await listOrders({status: 'pending_payment'});
  expect(result.items).toHaveLength(1);
  expect(fetch).toHaveBeenCalledWith(
    expect.stringMatching(/status=pending_payment/),
    expect.any(Object),
  );
});

it('cancelOrder posts to cancel endpoint', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({
      code: 0,
      message: 'ok',
      data: {...sampleOrder, status: 'cancelled'},
    }),
  });

  const result = await cancelOrder(10);
  expect(result.status).toBe('cancelled');
});

it('completeOrder posts to complete endpoint', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({
      code: 0,
      message: 'ok',
      data: {...sampleOrder, status: 'completed'},
    }),
  });

  const result = await completeOrder(10);
  expect(result.status).toBe('completed');
});

it('simulateFakeNotify throws when notify fails', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: false,
    status: 422,
  });

  await expect(simulateFakeNotify('OUT001')).rejects.toThrow(
    '模拟支付通知失败',
  );
});
