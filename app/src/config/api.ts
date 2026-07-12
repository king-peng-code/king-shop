import {Platform} from 'react-native';

const DEV_HOST = Platform.OS === 'android' ? '10.0.2.2' : 'localhost';

/** 真机调试时改为电脑局域网 IP，如 192.168.1.100 */
export const API_BASE_URL = `http://${DEV_HOST}:8000/api/v1`;
