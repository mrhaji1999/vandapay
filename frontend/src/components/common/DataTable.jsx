import clsx from 'clsx';

export default function DataTable({ columns, data, loading, emptyMessage, renderActions, striped = true }) {
  return (
    <div className="table-container" role="region">
      <div className="table-wrapper">
        <table className={clsx('data-table', { 'data-table-striped': striped })}>
          <thead>
            <tr>
              {columns.map((column) => (
                <th key={column.key || column.accessor}>{column.label}</th>
              ))}
              {renderActions && <th />}
            </tr>
          </thead>
          <tbody>
            {loading && (
              <tr>
                <td colSpan={columns.length + (renderActions ? 1 : 0)}>
                  <div className="table-skeleton">
                    {Array.from({ length: 4 }).map((_, index) => (
                      <div key={index} className="table-skeleton-row" />
                    ))}
                  </div>
                </td>
              </tr>
            )}
            {!loading && data.length === 0 && (
              <tr>
                <td colSpan={columns.length + (renderActions ? 1 : 0)}>
                  <div className="empty-state" style={{ boxShadow: 'none', padding: '24px' }}>
                    {emptyMessage}
                  </div>
                </td>
              </tr>
            )}
            {!loading &&
              data.map((row, index) => (
                <tr key={row.id || row.key || index}>
                  {columns.map((column) => (
                    <td key={column.key || column.accessor}>
                      {column.render ? column.render(row) : row[column.accessor]}
                    </td>
                  ))}
                  {renderActions && <td className="table-actions">{renderActions(row)}</td>}
                </tr>
              ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
