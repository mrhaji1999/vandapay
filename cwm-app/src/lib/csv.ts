export const exportToCsv = <T extends Record<string, unknown>>(filename: string, rows: T[]) => {
  if (!rows.length) return;
  const headers = Object.keys(rows[0]) as (keyof T)[];
  const csvContent = [
    headers.join(','),
    ...rows.map((row) =>
      headers
        .map((header) => JSON.stringify((row[header] as unknown) ?? ''))
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
