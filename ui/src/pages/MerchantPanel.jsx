import React, { useState, useEffect } from 'react';
import apiClient from '../lib/apiClient';
import Table from '../components/Table';
import Button from '../components/Button';
import Input from '../components/Input';
import { useAuth } from '../contexts/AuthContext';

const MerchantPanel = () => {
    const [balance, setBalance] = useState(0);
    const [nationalId, setNationalId] = useState('');
    const [amount, setAmount] = useState('');
    const [payoutAmount, setPayoutAmount] = useState('');
    const [payouts, setPayouts] = useState([]);
    const { token } = useAuth();

    useEffect(() => {
        const fetchBalance = async () => {
            try {
                const response = await apiClient.get('/wp-json/cwm/v1/wallet/balance', {
                    headers: { Authorization: `Bearer ${token}` },
                });
                setBalance(response.data.data.balance);
            } catch (error) {
                console.error('Failed to fetch balance:', error);
            }
        };

        const fetchPayouts = async () => {
            try {
                // Using dummy data for now
                setPayouts([
                    { id: 1, amount: 50, status: 'Completed', created_at: '2023-10-27' },
                    { id: 2, amount: 75, status: 'Pending', created_at: '2023-10-28' },
                ]);
            } catch (error) {
                console.error('Failed to fetch payouts:', error);
            }
        };

        if (token) {
            fetchBalance();
            fetchPayouts();
        }
    }, [token]);

    const handlePaymentRequest = async (e) => {
        e.preventDefault();
        try {
            await apiClient.post('/wp-json/cwm/v1/payment/request',
                { national_id: nationalId, amount },
                { headers: { Authorization: `Bearer ${token}` } }
            );
            alert('Payment request sent successfully!');
            setNationalId('');
            setAmount('');
        } catch (error) {
            console.error('Payment request failed:', error);
            alert('Failed to send payment request.');
        }
    };

    const handlePayoutRequest = async (e) => {
        e.preventDefault();
        // Placeholder for payout request
        alert(`Payout requested for amount: ${payoutAmount}`);
    };

    const payoutHeaders = ['ID', 'Amount', 'Status', 'Date'];
    const renderPayoutRow = (payout) => (
        <tr key={payout.id}>
            <td className="px-6 py-4">{payout.id}</td>
            <td className="px-6 py-4">{payout.amount}</td>
            <td className="px-6 py-4">{payout.status}</td>
            <td className="px-6 py-4">{payout.created_at}</td>
        </tr>
    );

    return (
        <div className="container mx-auto p-4">
            <h1 className="text-2xl font-bold mb-4">Merchant Panel</h1>

            <div className="mb-6 p-4 bg-white rounded-lg shadow">
                <h2 className="text-lg font-semibold">Current Balance: ${balance}</h2>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div className="p-4 bg-white rounded-lg shadow">
                    <h2 className="text-lg font-semibold mb-2">Request Payment</h2>
                    <form onSubmit={handlePaymentRequest} className="space-y-4">
                        <Input
                            value={nationalId}
                            onChange={(e) => setNationalId(e.target.value)}
                            placeholder="Employee National ID"
                            required
                        />
                        <Input
                            type="number"
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                            placeholder="Amount"
                            required
                        />
                        <Button type="submit">Request Payment</Button>
                    </form>
                </div>

                <div className="p-4 bg-white rounded-lg shadow">
                    <h2 className="text-lg font-semibold mb-2">Request Payout</h2>
                    <form onSubmit={handlePayoutRequest} className="space-y-4">
                        <Input
                            type="number"
                            value={payoutAmount}
                            onChange={(e) => setPayoutAmount(e.target.value)}
                            placeholder="Amount"
                            required
                        />
                        <Button type="submit">Request Payout</Button>
                    </form>
                </div>
            </div>

            <div className="p-4 bg-white rounded-lg shadow">
                <h2 className="text-lg font-semibold mb-2">Payout History</h2>
                <Table headers={payoutHeaders} data={payouts} renderRow={renderPayoutRow} />
            </div>

            <div className="p-4 mt-6 bg-white rounded-lg shadow">
                <h2 className="text-lg font-semibold mb-2">Payout Trends</h2>
                <div className="h-64 bg-gray-200 flex items-center justify-center">
                    <p>Chart will be here</p>
                </div>
            </div>
        </div>
    );
};

export default MerchantPanel;
