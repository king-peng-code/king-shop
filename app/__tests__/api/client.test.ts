import {apiRequest, ApiError, setOnUnauthorized, setTokenGetter} from '../../src/api/client';

global.fetch = jest.fn();

beforeEach(() => {
  (fetch as jest.Mock).mockReset();
  setTokenGetter(() => null);
  setOnUnauthorized(null);
});

it('returns data when code is 0', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({code: 0, message: 'ok', data: {id: 1}}),
  });

  const result = await apiRequest<{id: number}>('/health');
  expect(result).toEqual({id: 1});
});

it('throws ApiError when code is not 0', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 403,
    json: async () => ({code: 40301, message: '请先修改密码', data: null}),
  });

  await expect(apiRequest('/products')).rejects.toMatchObject({
    code: 40301,
    message: '请先修改密码',
  });
});

it('throws ApiError with code 401 on HTTP 401', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: false,
    status: 401,
    json: async () => ({code: 401, message: 'Unauthenticated', data: null}),
  });

  await expect(apiRequest('/auth/me')).rejects.toMatchObject({code: 401});
});

it('calls onUnauthorized when code is 401', async () => {
  const onUnauthorized = jest.fn();
  setOnUnauthorized(onUnauthorized);

  (fetch as jest.Mock).mockResolvedValue({
    ok: false,
    status: 401,
    json: async () => ({code: 401, message: 'Unauthenticated', data: null}),
  });

  await expect(apiRequest('/auth/me')).rejects.toMatchObject({code: 401});
  expect(onUnauthorized).toHaveBeenCalledTimes(1);
});

it('sends Authorization header when token exists', async () => {
  setTokenGetter(() => 'test-token');
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({code: 0, message: 'ok', data: {}}),
  });

  await apiRequest('/auth/me');

  expect(fetch).toHaveBeenCalledWith(
    expect.stringContaining('/auth/me'),
    expect.objectContaining({
      headers: expect.objectContaining({
        Authorization: 'Bearer test-token',
      }),
    }),
  );
});
