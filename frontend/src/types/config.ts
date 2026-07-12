export interface ConfigItem {
  key: string;
  value: string;
  is_sensitive: boolean;
  is_readonly?: boolean;
  description: string | null;
}

export interface ConfigGroup {
  name: string;
  label: string;
  items: ConfigItem[];
}

export interface ConfigListResult {
  groups: ConfigGroup[];
}

export interface ConfigUpdatePayload {
  group: string;
  key: string;
  value: string;
}
