import React, { useState, useEffect } from 'react';
import axios from 'axios';
import Table from '../components/Table';
import Button from '../components/Button';
import Input from '../components/Input';
import { useAuth } from '../contexts/AuthContext';

const EmployeePanel = () => {
    const [balance, setBalance] = useState(0);
    const [pendingRequests, setPendingRequests] = useState([]);
    const [transactions, setTransactions] = useState([]);
    const [otpValues, setOtpValues] = useState({});
    const { token } = useAuth();

    useEffect(() => {
        const fetchBalance = async () => {
            try {
                const response = await axios.get('/wp-json/cwm/v1/wallet/balance', {
                    headers: { Authorization: `Bearer ${token}` },
                });
                setBalance(response.data.data.balance);
            } catch (error) {
                console.error('Failed to fetch balance:', error);
            }
        };

        const fetchPendingRequests = () => {
            // Placeholder data
            setPendingRequests([
                { id: 1, merchant_name: 'SuperMart', amount: 25 },
                { id: 2, merchant_name: 'CoffeeShop', amount: 10 },
            ]);
        };

        const fetchTransactions = () => {
            // Placeholder data
            setTransactions([
                { id: 1, type: 'transfer', amount: 50, status: 'Completed', created_at: '2023-10-26' },
                { id: 2, type: 'charge', amount: 200, status: 'Completed', created_at: '2023-10-25' },
            ]);
        };

        if (token) {
            fetchBalance();
            fetchPendingRequests();
            fetchTransactions();
        }
    }, [token]);

    const handleOtpChange = (requestId, value) => {
        setOtpValues(prev => ({ ...prev, [requestId]: value }));
    };

    const handleConfirmPayment = async (requestId) => {
        const otp_code = otpValues[requestId];
        if (!otp_code) {
            alert('Please enter the OTP code.');
            return;
        }

        try {
            await axios.post('/wp-json/cwm/v1/payment/confirm',
                { request_id: requestId, otp_code },
                { headers: { Authorization: `Bearer ${token}` } }
            );
            alert('Payment confirmed successfully!');
            // Refresh pending requests and balance
        } catch (error) {
            console.error('Payment confirmation failed:', error);
            alert('Failed to confirm payment.');
        }
    };

    const requestHeaders = ['Merchant', 'Amount', 'OTP', 'Action'];
    const renderRequestRow = (request) => (
        <tr key={request.id}>
            <td className="px-6 py-4">{request.merchant_name}</td>
            <td className="px-6 py-4">${request.amount}</td>
            <td className="px-6 py-4">
                <Input
                    type="text"
                    placeholder="Enter OTP"
                    value={otpValues[request.id] || ''}
                    onChange={(e) => handleOtpChange(request.id, e.target.value)}
                />
            </td>
            <td className="px-6 py-4">
                <Button onClick={() => handleConfirmPayment(request.id)}>Confirm</Button>
            </td>
        </tr>
    );

    const transactionHeaders = ['ID', 'Type', 'Amount', 'Status', 'Date'];
    const renderTransactionRow = (transaction) => (
        <tr key={transaction.id}>
            <td className="px-6 py-4">{transaction.id}</td>
            <td className="px-6 py-4">{transaction.type}</td>
            <td className="px-6 py-4">{transaction.amount}</td>
            <td className="px-6 py-4">{transaction.status}</td>
            <td className="px-6 py-4">{transaction.created_at}</td>
        </tr>
    );

    return (
        <div className="container mx-auto p-4">
            <h1 className="text-2xl font-bold mb-4">Employee Panel</h1>

            <div className="mb-6 p-4 bg-white rounded-lg shadow">
                <h2 className="text-lg font-semibold">Current Balance: ${balance}</h2>
            </div>

            <div className="p-4 bg-white rounded-lg shadow mb-6">
                <h2 className="text-lg font-semibold mb-2">Pending Payment Requests</h2>
                <Table headers={requestHeaders} data={pendingRequests} renderRow={renderRequestRow} />
            </div>

            <div className="p-4 bg-white rounded-lg shadow">
                <h2 className="text-lg font-semibold mb-2">Transaction History</h2>
                <Table headers={transactionHeaders} data={transactions} renderRow={renderTransactionRow} />
            </div>
        </div>
    );
};

export default EmployeePanel;
