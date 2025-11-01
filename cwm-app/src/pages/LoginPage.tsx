import { FormEvent, useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import toast from 'react-hot-toast';
import { AuthLayout } from '../components/layout/AuthLayout';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Button } from '../components/ui/button';
import { apiClient } from '../api/client';
import { decodeRole, useAuth } from '../store/auth';

export const LoginPage = () => {
  const navigate = useNavigate();
  const { setToken, fetchProfile, setUser } = useAuth();
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const username = String(form.get('username') ?? '');
    const password = String(form.get('password') ?? '');
    setLoading(true);

    try {
      const response = await apiClient.post<{ token: string }>('/token', { username, password });
      const token = response.data.token;
      setToken(token);
      const profile = await fetchProfile();
      if (!profile) {
        const role = decodeRole(token);
        setUser({
          id: 0,
          username,
          email: '',
          role: role ?? undefined
        });
      }
      toast.success('Logged in successfully');
      const role = decodeRole(token) ?? profile?.role ?? profile?.roles?.[0] ?? 'employee';
      navigate(`/${role}`, { replace: true });
    } catch (error) {
      console.error(error);
      toast.error('Invalid username or password');
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthLayout>
      <form className="space-y-4" onSubmit={handleSubmit}>
        <div className="space-y-2">
          <Label htmlFor="username">Username</Label>
          <Input id="username" name="username" placeholder="Enter your username" required autoComplete="username" />
        </div>
        <div className="space-y-2">
          <Label htmlFor="password">Password</Label>
          <Input
            id="password"
            name="password"
            type="password"
            placeholder="Enter your password"
            required
            autoComplete="current-password"
          />
        </div>
        <Button className="w-full" type="submit" disabled={loading}>
          {loading ? 'Signing in…' : 'Sign in'}
        </Button>
        <div className="text-center text-sm">
          <p>
            Need an account?{' '}
            <Link className="text-primary underline" to="/register/company">
              Register your company
            </Link>{' '}
            or{' '}
            <Link className="text-primary underline" to="/register/merchant">
              register as merchant
            </Link>
            .
          </p>
        </div>
      </form>
    </AuthLayout>
  );
};
