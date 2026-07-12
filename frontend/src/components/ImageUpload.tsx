import { PlusOutlined } from '@ant-design/icons';
import { Upload, message } from 'antd';
import type { UploadProps } from 'antd';
import { uploadImage } from '../api/upload';
import { ApiError } from '../api/client';
import { resolveMediaUrl } from '../utils/mediaUrl';

export interface ImageUploadValue {
  uploadId: number | null;
  previewUrl: string | null;
}

interface Props {
  value?: ImageUploadValue;
  onChange?: (value: ImageUploadValue | null) => void;
  onImageChange?: () => void;
}

const MAX_SIZE = 2 * 1024 * 1024;
const ACCEPT = 'image/jpeg,image/jpg,image/png,image/webp';

export default function ImageUpload({ value, onChange, onImageChange }: Props) {
  const previewUrl = resolveMediaUrl(value?.previewUrl);
  const fileList = previewUrl
    ? [
        {
          uid: '-1',
          name: 'cover',
          status: 'done' as const,
          url: previewUrl,
        },
      ]
    : [];

  const beforeUpload: UploadProps['beforeUpload'] = (file) => {
    if (!file.type.startsWith('image/')) {
      message.error('只能上传图片文件');
      return Upload.LIST_IGNORE;
    }
    if (file.size > MAX_SIZE) {
      message.error('图片大小不能超过 2MB');
      return Upload.LIST_IGNORE;
    }
    return true;
  };

  const customRequest: UploadProps['customRequest'] = async (options) => {
    const { file, onSuccess, onError } = options;
    try {
      const result = await uploadImage(file as File);
      onChange?.({ uploadId: result.id, previewUrl: resolveMediaUrl(result.url) });
      onImageChange?.();
      onSuccess?.(result);
    } catch (e) {
      if (e instanceof ApiError) {
        message.error(e.message);
      } else {
        message.error('上传失败');
      }
      onError?.(e as Error);
    }
  };

  return (
    <Upload
      listType="picture-card"
      fileList={fileList}
      maxCount={1}
      accept={ACCEPT}
      beforeUpload={beforeUpload}
      customRequest={customRequest}
      onRemove={() => {
        onChange?.(null);
        onImageChange?.();
        return true;
      }}
    >
      {fileList.length >= 1 ? null : (
        <div>
          <PlusOutlined />
          <div style={{ marginTop: 8 }}>上传封面</div>
        </div>
      )}
    </Upload>
  );
}
