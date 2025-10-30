import React, { useState, useEffect } from 'react';
import axios from 'axios';
import Table from '../components/Table';
import Button from '../components/Button';
import Input from '../components/Input';
import { useAuth } from '../contexts/AuthContext';

const CompanyPanel = () => {
    const [employees, setEmployees] = useState([]);
    const [chargeAmount, setChargeAmount] = useState('');
    const { token } = useAuth();

    // Fetch employees on component mount
    useEffect(() => {
        const fetchEmployees = async () => {
            try {
                // This is a placeholder endpoint. The API needs to be extended
                // to support fetching a list of employees for a company.
                // const response = await axios.get('/wp-json/cwm/v1/employees', {
                //     headers: { Authorization: `Bearer ${token}` },
                // });
                // setEmployees(response.data.data);

                // Using dummy data for now
                setEmployees([
                    { id: 1, name: 'John Doe', national_id: '1234567890', wallet_balance: 100 },
                    { id: 2, name: 'Jane Smith', national_id: '0987654321', wallet_balance: 150 },
                ]);
            } catch (error) {
                console.error('Failed to fetch employees:', error);
            }
        };

        if (token) {
            fetchEmployees();
        }
    }, [token]);

    const handleChargeUsers = () => {
        // This would call the /wallet/charge endpoint for each selected employee.
        // For now, it's a placeholder.
        alert(`Charging users with amount: ${chargeAmount}`);
    };

    const headers = ['Name', 'National ID', 'Wallet Balance'];
    const renderRow = (employee) => (
        <tr key={employee.id}>
            <td className="px-6 py-4 whitespace-nowrap">{employee.name}</td>
            <td className="px-6 py-4 whitespace-nowrap">{employee.national_id}</td>
            <td className="px-6 py-4 whitespace-nowrap">{employee.wallet_balance}</td>
        </tr>
    );

    return (
        <div className="container mx-auto p-4">
            <h1 className="text-2xl font-bold mb-4">Company Panel</h1>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div className="p-4 bg-white rounded-lg shadow">
                    <h2 className="text-lg font-semibold mb-2">Upload Employee CSV</h2>
                    <input type="file" className="file-input w-full max-w-xs" />
                    <Button className="mt-2">Upload & Add Users</Button>
                </div>

                <div className="p-4 bg-white rounded-lg shadow">
                    <h2 className="text-lg font-semibold mb-2">Charge All Employees</h2>
                    <Input
                        type="number"
                        value={chargeAmount}
                        onChange={(e) => setChargeAmount(e.target.value)}
                        placeholder="Enter amount to charge"
                    />
                    <Button onClick={handleChargeUsers} className="mt-2">
                        Add & Charge Users
                    </Button>
                </div>
            </div>

            <div className="p-4 bg-white rounded-lg shadow">
                <h2 className="text-lg font-semibold mb-2">Employees</h2>
                <Table headers={headers} data={employees} renderRow={renderRow} />
            </div>

            {/* Placeholder for the chart */}
            <div className="p-4 mt-6 bg-white rounded-lg shadow">
                <h2 className="text-lg font-semibold mb-2">Total Funds by Employee</h2>
                <div className="h-64 bg-gray-200 flex items-center justify-center">
                    <p>Chart will be here</p>
                </div>
            </div>
        </div>
    );
};

export default CompanyPanel;
