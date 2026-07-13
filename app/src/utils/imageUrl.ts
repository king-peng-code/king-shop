import {API_BASE_URL} from '../config/api';

function parseBaseUrl(): URL {
  try {
    return new URL(API_BASE_URL);
  } catch {
    return new URL('http://localhost:8000');
  }
}

let cachedImageHost: string | null = null;

function getImageHost(): string {
  if (cachedImageHost) {
    return cachedImageHost;
  }
  const url = parseBaseUrl();
  cachedImageHost = url.host;
  return cachedImageHost;
}

const LOCALHOST_PATTERNS = [
  /^http:\/\/localhost(:\d+)?/i,
  /^http:\/\/127\.0\.0\.1(:\d+)?/,
];

function isLocalhostUrl(urlStr: string): boolean {
  return LOCALHOST_PATTERNS.some(p => p.test(urlStr));
}

/**
 * 将后端返回的 image_url 中的 localhost/127.0.0.1 替换为
 * API_BASE_URL 中配置的实际主机地址（如 10.0.2.2），
 * 确保 Android 模拟器和真机都能正确加载图片。
 */
export function fixImageUrl(urlStr: string | null | undefined): string | null {
  if (!urlStr) {
    return null;
  }

  try {
    const parsed = new URL(urlStr);

    if (isLocalhostUrl(urlStr)) {
      parsed.host = getImageHost();
      return parsed.toString();
    }

    return urlStr;
  } catch {
    return urlStr;
  }
}
