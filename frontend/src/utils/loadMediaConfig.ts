import { configsApi } from '../api/configs';
import { applyMediaConfigFromGroups } from './mediaUrl';

export async function loadMediaConfig(): Promise<void> {
  const result = await configsApi.get();
  applyMediaConfigFromGroups(result.groups);
}
