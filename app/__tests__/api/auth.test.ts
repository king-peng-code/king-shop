import {login, getMe, changePassword} from '../../src/api/auth';
import {setTokenGetter} from '../../src/api/client';

global.fetch = jest.fn();

beforeEach(() => {
  (fetch as jest.Mock).mockReset();
  setTokenGetter(() => 'tok');
});

it('login posts credentials and returns result', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({
      code: 0,
      message: 'ok',
      data: {
        token: '1|abc',
        user: {id: 1, name: '张三', phone: '13800000001', must_change_password: true},
        must_change_password: true,
      },
    }),
  });

  const result = await login('13800000001', '123456');
  expect(result.token).toBe('1|abc');
  expect(result.must_change_password).toBe(true);
  expect(fetch).toHaveBeenCalledWith(
    expect.stringContaining('/auth/login'),
    expect.objectContaining({method: 'POST'}),
  );
});

it('changePassword sends PUT body', async () => {
  (fetch as jest.Mock).mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => ({code: 0, message: 'ok', data: null}),
  });

  await changePassword('123456', 'newpass1');
  expect(fetch).toHaveBeenCalledWith(
    expect.stringContaining('/auth/password'),
    expect.objectContaining({
      method: 'PUT',
      body: JSON.stringify({
        current_password: '123456',
        new_password: 'newpass1',
        new_password_confirmation: 'newpass1',
      }),
    }),
  );
});
