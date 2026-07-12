export interface ExternalUser {
  id: number;
  provider: string;
  external_id: string;
  name: string | null;
  phone: string | null;
  tags: string[];
}

export interface UpdateExternalUserPayload {
  name?: string | null;
  phone?: string | null;
  tags?: string[];
}
