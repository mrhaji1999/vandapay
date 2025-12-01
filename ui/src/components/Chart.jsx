import React from 'react';
import { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';

const Chart = ({ data, type = 'line', dataKey = 'revenue', color = '#0ea5e9', title }) => {
    const ChartComponent = type === 'bar' ? BarChart : LineChart;
    const DataComponent = type === 'bar' ? Bar : Line;

    return (
        <div className="w-full h-80">
            {title && (
                <h3 className="text-lg font-semibold text-white mb-4">{title}</h3>
            )}
            <ResponsiveContainer width="100%" height="100%">
                <ChartComponent data={data} margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#334155" />
                    <XAxis 
                        dataKey="label" 
                        stroke="#94a3b8"
                        style={{ fontSize: '12px' }}
                    />
                    <YAxis 
                        stroke="#94a3b8"
                        style={{ fontSize: '12px' }}
                        tickFormatter={(value) => `${(value / 1000).toFixed(0)}K`}
                    />
                    <Tooltip 
                        contentStyle={{ 
                            backgroundColor: '#1e293b', 
                            border: '1px solid #334155',
                            borderRadius: '8px',
                            color: '#e2e8f0'
                        }}
                        formatter={(value) => [`${value.toLocaleString('fa-IR')} ریال`, 'درآمد']}
                    />
                    <Legend 
                        wrapperStyle={{ color: '#94a3b8' }}
                    />
                    <DataComponent 
                        type="monotone" 
                        dataKey={dataKey} 
                        stroke={color}
                        fill={color}
                        strokeWidth={2}
                        dot={{ fill: color, r: 4 }}
                        activeDot={{ r: 6 }}
                    />
                </ChartComponent>
            </ResponsiveContainer>
        </div>
    );
};

export default Chart;

