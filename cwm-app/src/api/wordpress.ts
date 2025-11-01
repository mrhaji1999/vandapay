const isObject = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null;

export type WordPressSuccessResponse<T> = {
  status?: string;
  data?: T;
  count?: number;
  message?: string;
};

export const unwrapWordPressData = <T>(payload: unknown): T | undefined => {
  if (isObject(payload) && 'data' in payload) {
    const data = (payload as WordPressSuccessResponse<T>).data;
    return data as T | undefined;
  }

  return payload as T | undefined;
};

export const unwrapWordPressList = <T>(payload: unknown): T[] => {
  const data = unwrapWordPressData<unknown>(payload);

  if (Array.isArray(data)) {
    return data as T[];
  }

  return [];
};

export const unwrapWordPressObject = <T>(payload: unknown): T | null => {
  const data = unwrapWordPressData<unknown>(payload);

  if (isObject(data)) {
    return data as T;
  }

  return null;
};
