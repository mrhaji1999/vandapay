import * as Dialog from '@radix-ui/react-dialog';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Label } from '../ui/label';
import { cn } from '../../utils/cn';

interface OTPModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (values: { requestId: string; otp: string }) => void;
  isSubmitting?: boolean;
}

export const OTPModal = ({ open, onOpenChange, onSubmit, isSubmitting }: OTPModalProps) => {
  const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    onSubmit({
      requestId: String(form.get('requestId') ?? ''),
      otp: String(form.get('otp') ?? '')
    });
  };

  return (
    <Dialog.Root open={open} onOpenChange={onOpenChange}>
      <Dialog.Portal>
        <Dialog.Overlay className="fixed inset-0 z-40 bg-black/40" />
        <Dialog.Content
          className={cn(
            'fixed left-1/2 top-1/2 z-50 w-[90vw] max-w-md -translate-x-1/2 -translate-y-1/2 rounded-lg border bg-white p-6 shadow-lg'
          )}
        >
          <Dialog.Title className="text-lg font-semibold">تأیید پرداخت</Dialog.Title>
          <Dialog.Description className="mt-1 text-sm text-muted-foreground">
            شناسه درخواست و رمز یکبار مصرف ارسال‌شده را وارد کنید.
          </Dialog.Description>
          <form className="mt-4 space-y-4" onSubmit={handleSubmit}>
            <div className="space-y-2">
              <Label htmlFor="requestId">شناسه درخواست</Label>
              <Input id="requestId" name="requestId" required placeholder="REQ-123" />
            </div>
            <div className="space-y-2">
              <Label htmlFor="otp">کد تأیید</Label>
              <Input id="otp" name="otp" required placeholder="123456" />
            </div>
            <div className="flex justify-end gap-2">
              <Dialog.Close asChild>
                <Button type="button" variant="ghost">
                  انصراف
                </Button>
              </Dialog.Close>
              <Button type="submit" disabled={isSubmitting}>
                تأیید
              </Button>
            </div>
          </form>
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  );
};
