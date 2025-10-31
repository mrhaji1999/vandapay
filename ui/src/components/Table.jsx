import React from 'react';

const Table = ({ headers, data, renderRow, emptyMessage = 'موردی برای نمایش وجود ندارد.' }) => {
    return (
        <div className="overflow-hidden rounded-3xl border border-white/10 bg-white/5 shadow-[0_25px_45px_-35px_rgba(15,23,42,0.8)]">
            <div className="overflow-x-auto">
                <table className="min-w-full text-left text-sm text-slate-100">
                    <thead className="bg-white/5 text-xs uppercase tracking-wide text-slate-300">
                        <tr>
                            {headers.map((header) => (
                                <th key={header} scope="col" className="px-6 py-3 font-medium">
                                    {header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-white/5">
                        {data.length > 0 ? (
                            data.map((item, index) => renderRow(item, index))
                        ) : (
                            <tr>
                                <td colSpan={headers.length} className="px-6 py-6 text-center text-sm text-slate-400">
                                    {emptyMessage}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default Table;
