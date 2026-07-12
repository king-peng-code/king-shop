import {getOrder} from '../api/orders';

export async function pollOrderStatus(
  orderId: number,
  options?: {intervalMs?: number; maxAttempts?: number},
): Promise<'paid' | 'timeout'> {
  const intervalMs = options?.intervalMs ?? 2000;
  const maxAttempts = options?.maxAttempts ?? 30;

  for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
    const order = await getOrder(orderId);
    if (order.status === 'paid') {
      return 'paid';
    }
    if (attempt < maxAttempts - 1) {
      await new Promise<void>(resolve => {
        setTimeout(resolve, intervalMs);
      });
    }
  }

  return 'timeout';
}
