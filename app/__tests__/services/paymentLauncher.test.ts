import {simulateFakeNotify} from '../../src/api/orders';
import {
  isAlipayParams,
  isFakeParams,
  isWechatParams,
  launchFakePay,
  launchWechatPay,
} from '../../src/services/paymentLauncher';

jest.mock('../../src/api/orders', () => ({
  simulateFakeNotify: jest.fn(),
}));

jest.mock('react-native-wechat-lib', () => ({
  registerApp: jest.fn(),
  isWXAppInstalled: jest.fn(),
  pay: jest.fn(),
}));

jest.mock('../../src/config/payment', () => ({
  WECHAT_APP_ID: 'wx_test_app_id',
}));

const WeChat = jest.requireMock('react-native-wechat-lib') as {
  registerApp: jest.Mock;
  isWXAppInstalled: jest.Mock;
  pay: jest.Mock;
};

const prepay = {
  appid: 'wx_test',
  partnerid: '123456',
  prepayid: 'prepay123',
  package: 'Sign=WXPay',
  noncestr: 'nonce',
  timestamp: '1234567890',
  sign: 'SIGN',
};

beforeEach(() => {
  jest.clearAllMocks();
  WeChat.isWXAppInstalled.mockResolvedValue(true);
  WeChat.pay.mockResolvedValue(undefined);
  (simulateFakeNotify as jest.Mock).mockResolvedValue(undefined);
});

describe('pay param type guards', () => {
  it('isFakeParams narrows fake channel', () => {
    const params = {
      channel: 'fake' as const,
      out_trade_no: 'OUT001',
      amount: 100,
      order_no: 'KS001',
    };
    expect(isFakeParams(params)).toBe(true);
    expect(isAlipayParams(params)).toBe(false);
  });

  it('isAlipayParams narrows alipay channel', () => {
    const params = {channel: 'alipay_sandbox' as const, pay_url: 'https://pay'};
    expect(isAlipayParams(params)).toBe(true);
    expect(isWechatParams(params)).toBe(false);
  });

  it('isWechatParams narrows wechat channel', () => {
    const params = {channel: 'wechat' as const, prepay};
    expect(isWechatParams(params)).toBe(true);
    expect(isFakeParams(params)).toBe(false);
  });
});

describe('launchFakePay', () => {
  it('delegates to simulateFakeNotify', async () => {
    await launchFakePay('OUT001');
    expect(simulateFakeNotify).toHaveBeenCalledWith('OUT001');
  });
});

describe('launchWechatPay', () => {
  it('returns success when WeChat.pay resolves', async () => {
    const result = await launchWechatPay(prepay);
    expect(result).toBe('success');
    expect(WeChat.registerApp).toHaveBeenCalledWith('wx_test_app_id', '');
    expect(WeChat.pay).toHaveBeenCalledWith(
      expect.objectContaining({
        partnerId: '123456',
        prepayId: 'prepay123',
      }),
    );
  });

  it('returns cancelled when err code is -2', async () => {
    WeChat.pay.mockRejectedValue({code: -2, message: 'cancelled'});
    const result = await launchWechatPay(prepay);
    expect(result).toBe('cancelled');
  });

  it('returns failed for other errors', async () => {
    WeChat.pay.mockRejectedValue({code: -1, message: 'fail'});
    const result = await launchWechatPay(prepay);
    expect(result).toBe('failed');
  });

  it('throws when WeChat is not installed', async () => {
    WeChat.isWXAppInstalled.mockResolvedValue(false);
    await expect(launchWechatPay(prepay)).rejects.toThrow('未安装微信客户端');
  });
});
