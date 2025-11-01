type RowRecord = Record<string, unknown>;

export const exportToCsv = <T extends object>(filename: string, rows: T[]) => {
  if (!rows.length) return;
  const headers = Object.keys(rows[0] as RowRecord) as Array<Extract<keyof T, string>>;
  const csvContent = [
    headers.join(','),
    ...rows.map((row) =>
      headers
        .map((header) => {
          const value = (row as RowRecord)[header];
          return JSON.stringify(value ?? '');
        })
        .join(',')
    )
  ].join('\n');

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  link.href = url;
  link.setAttribute('download', filename);
  link.click();
  URL.revokeObjectURL(url);
};
