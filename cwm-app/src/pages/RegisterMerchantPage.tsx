import { FormEvent, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { AuthLayout } from '../components/layout/AuthLayout';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Button } from '../components/ui/button';
import { apiClient } from '../api/client';

const FIELDS: { name: string; label: string; type?: string }[] = [
  { name: 'full_name', label: 'Full name' },
  { name: 'store_name', label: 'Store name' },
  { name: 'store_address', label: 'Store address' },
  { name: 'phone', label: 'Phone' },
  { name: 'email', label: 'Email', type: 'email' },
  { name: 'mobile', label: 'Mobile' },
  { name: 'password', label: 'Password', type: 'password' }
];

export const RegisterMerchantPage = () => {
  const navigate = useNavigate();
  const [formState, setFormState] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const payload: Record<string, string> = {};
    const persisted: Record<string, string> = {};
    for (const [key, value] of form.entries()) {
      const stringValue = String(value);
      payload[key] = stringValue;
      persisted[key] = stringValue;
    }
    setFormState(persisted);
    setLoading(true);
    try {
      await apiClient.post('/public/merchant/register', payload);
      toast.success('Registration successful. Please sign in.');
      navigate('/login', { replace: true });
    } catch (error) {
      const status = (error as any)?.response?.status;
      if (status === 500 || status === 404) {
        toast.error('Registration service is temporarily unavailable.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthLayout>
      <form className="space-y-4" onSubmit={handleSubmit}>
        {FIELDS.map((field) => (
          <div key={field.name} className="space-y-2">
            <Label htmlFor={field.name}>{field.label}</Label>
            <Input
              id={field.name}
              name={field.name}
              type={field.type ?? 'text'}
              required
              defaultValue={formState[field.name] ?? ''}
            />
          </div>
        ))}
        <Button type="submit" className="w-full" disabled={loading}>
          {loading ? 'Submitting…' : 'Submit'}
        </Button>
      </form>
    </AuthLayout>
  );
};
