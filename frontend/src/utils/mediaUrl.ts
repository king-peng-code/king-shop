import type { ConfigGroup } from '../types/config';

type StorageDriver = 'local' | 'oss';

let storageDriver: StorageDriver = 'local';
let localPublicBaseUrl: string | null = null;
let ossPublicBaseUrl: string | null = null;

function trimBaseUrl(url: string): string {
  return url.replace(/\/$/, '');
}

export function configureMediaUrls(options: {
  driver?: string;
  localPublicBaseUrl?: string;
  ossPublicBaseUrl?: string;
}): void {
  if (options.driver === 'local' || options.driver === 'oss') {
    storageDriver = options.driver;
  }
  if (options.localPublicBaseUrl !== undefined) {
    const trimmed = trimBaseUrl(options.localPublicBaseUrl);
    localPublicBaseUrl = trimmed || null;
  }
  if (options.ossPublicBaseUrl !== undefined) {
    const trimmed = trimBaseUrl(options.ossPublicBaseUrl);
    ossPublicBaseUrl = trimmed || null;
  }
}

function configValue(groups: ConfigGroup[], group: string, key: string): string {
  const configGroup = groups.find((g) => g.name === group);
  const item = configGroup?.items.find((i) => i.key === key);
  return item?.value ?? '';
}

export function applyMediaConfigFromGroups(groups: ConfigGroup[]): void {
  configureMediaUrls({
    driver: configValue(groups, 'storage', 'driver') || 'local',
    localPublicBaseUrl: configValue(groups, 'storage', 'local.public_base_url'),
    ossPublicBaseUrl: configValue(groups, 'storage', 'oss.public_base_url'),
  });
}

function activePublicBaseUrl(): string | null {
  if (storageDriver === 'oss') {
    return ossPublicBaseUrl;
  }
  return localPublicBaseUrl;
}

/** Resolve storage image URLs using configured public base URL (not APP_URL). */
export function resolveMediaUrl(url: string | null | undefined): string | null {
  if (!url) {
    return null;
  }

  const base = activePublicBaseUrl();
  if (!base) {
    return url.startsWith('http://') || url.startsWith('https://') ? url : null;
  }

  if (url.startsWith('/storage/')) {
    return `${base}${url}`;
  }

  if (url.startsWith('storage/')) {
    return `${base}/${url}`;
  }

  const storagePathMatch = url.match(/^https?:\/\/[^/]+(\/storage\/.+)$/);
  if (storagePathMatch) {
    return `${base}${storagePathMatch[1]}`;
  }

  if (storageDriver === 'oss') {
    if (url.startsWith('uploads/')) {
      return `${base}/${url}`;
    }
    const ossPathMatch = url.match(/^https?:\/\/[^/]+\/(uploads\/.+)$/);
    if (ossPathMatch) {
      return `${base}/${ossPathMatch[1]}`;
    }
  }

  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url;
  }

  return `${base}/storage/${url.replace(/^\//, '')}`;
}
