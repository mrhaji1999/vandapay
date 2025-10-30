import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import LoginPage from './pages/LoginPage';
import CompanyPanel from './pages/CompanyPanel';
import MerchantPanel from './pages/MerchantPanel';
import EmployeePanel from './pages/EmployeePanel';
import ProtectedRoute from './components/ProtectedRoute';

// A placeholder for a dashboard component that would handle role-based redirection.
const Dashboard = () => {
    const { token } = useAuth();
    // In a real app, you'd decode the JWT here to get the user's role.
    // For now, we'll just show a generic dashboard message.
    // Or, we can default to one of the panels for demonstration.
    // For example, let's assume the logged-in user is a company for now.

    // This logic should be improved to decode the JWT and get the actual role.
    // For this example, I'll just render the CompanyPanel as a default.
    return <CompanyPanel />;
};

function App() {
    return (
        <AuthProvider>
            <Router>
                <Routes>
                    <Route path="/login" element={<LoginPage />} />
                    <Route
                        path="/dashboard"
                        element={
                            <ProtectedRoute>
                                <Dashboard />
                            </ProtectedRoute>
                        }
                    />
                    <Route
                        path="/company"
                        element={
                            <ProtectedRoute>
                                <CompanyPanel />
                            </ProtectedRoute>
                        }
                    />
                     <Route
                        path="/merchant"
                        element={
                            <ProtectedRoute>
                                <MerchantPanel />
                            </ProtectedRoute>
                        }
                    />
                     <Route
                        path="/employee"
                        element={
                            <ProtectedRoute>
                                <EmployeePanel />
                            </ProtectedRoute>
                        }
                    />
                    <Route path="*" element={<Navigate to="/login" />} />
                </Routes>
            </Router>
        </AuthProvider>
    );
}

export default App;
