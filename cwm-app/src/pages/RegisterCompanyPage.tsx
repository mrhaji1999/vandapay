import { FormEvent, useMemo, useState } from 'react';
import toast from 'react-hot-toast';
import { AuthLayout } from '../components/layout/AuthLayout';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { Button } from '../components/ui/button';
import { apiClient } from '../api/client';

type CompanyType = 'legal' | 'real';

type FormState = Record<string, string>;

const LEGAL_FIELDS: { name: string; label: string; type?: string }[] = [
  { name: 'company_name', label: 'Company name' },
  { name: 'company_email', label: 'Company email', type: 'email' },
  { name: 'company_phone', label: 'Company phone' },
  { name: 'economic_code', label: 'Economic code' },
  { name: 'national_id', label: 'National ID' },
  { name: 'password', label: 'Password', type: 'password' }
];

const REAL_FIELDS: { name: string; label: string; type?: string }[] = [
  { name: 'full_name', label: 'Full name' },
  { name: 'email', label: 'Email', type: 'email' },
  { name: 'phone', label: 'Phone' },
  { name: 'password', label: 'Password', type: 'password' }
];

export const RegisterCompanyPage = () => {
  const [companyType, setCompanyType] = useState<CompanyType>('legal');
  const [formState, setFormState] = useState<FormState>({});
  const [loading, setLoading] = useState(false);

  const fields = useMemo(() => (companyType === 'legal' ? LEGAL_FIELDS : REAL_FIELDS), [companyType]);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const payload: Record<string, string> = { company_type: companyType };
    const persisted: FormState = {};
    for (const [key, value] of form.entries()) {
      const stringValue = String(value);
      payload[key] = stringValue;
      persisted[key] = stringValue;
    }
    setFormState(persisted);
    setLoading(true);
    try {
      await apiClient.post('/public/company/register', payload);
      toast.success('Your request is submitted and awaits admin approval.');
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
      <div className="mb-4 flex items-center justify-center gap-2 rounded-md bg-secondary p-1">
        <Button
          type="button"
          variant={companyType === 'legal' ? 'default' : 'ghost'}
          onClick={() => setCompanyType('legal')}
          className="w-full"
        >
          Corporate
        </Button>
        <Button
          type="button"
          variant={companyType === 'real' ? 'default' : 'ghost'}
          onClick={() => setCompanyType('real')}
          className="w-full"
        >
          Individual
        </Button>
      </div>
      <form className="space-y-4" onSubmit={handleSubmit}>
        {fields.map((field) => (
          <div key={field.name} className="space-y-2">
            <Label htmlFor={field.name}>{field.label}</Label>
            <Input
              id={field.name}
              name={field.name}
              type={field.type ?? 'text'}
              defaultValue={formState[field.name] ?? ''}
              required
            />
          </div>
        ))}
        <Button className="w-full" type="submit" disabled={loading}>
          {loading ? 'Submitting…' : 'Submit'}
        </Button>
      </form>
    </AuthLayout>
  );
};
