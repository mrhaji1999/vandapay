import { useMemo } from 'react';
import { cn } from '../../utils/cn';

interface CreditCardProps {
  balance: number;
  cardHolderName?: string;
  phoneNumber?: string;
  className?: string;
}

export const CreditCard = ({ balance, cardHolderName = 'کاربر', phoneNumber, className }: CreditCardProps) => {
  const formattedBalance = useMemo(() => {
    return new Intl.NumberFormat('fa-IR', {
      style: 'decimal',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0
    }).format(balance);
  }, [balance]);

  // Format phone number (remove any non-digit characters and display without stars)
  const formattedPhoneNumber = useMemo(() => {
    if (!phoneNumber) {
      // Generate a default phone number based on cardHolderName hash if not provided
      let hash = 0;
      for (let i = 0; i < cardHolderName.length; i++) {
        const char = cardHolderName.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash = hash & hash;
      }
      const phone = `0912${String(Math.abs(hash % 10000000)).padStart(7, '0')}`;
      return phone;
    }
    // Remove any non-digit characters
    const digits = phoneNumber.replace(/\D/g, '');
    return digits;
  }, [phoneNumber, cardHolderName]);

  // Generate expiration date (12 months from now)
  const expirationDate = useMemo(() => {
    const date = new Date();
    date.setMonth(date.getMonth() + 12);
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = String(date.getFullYear()).slice(-2);
    return `${month}/${year}`;
  }, []);

  return (
    <div
      className={cn(
        'relative w-full rounded-2xl overflow-hidden shadow-2xl',
        'bg-gradient-to-br from-blue-500 via-blue-600 to-blue-700',
        'text-white',
        className
      )}
      style={{
        aspectRatio: '16/9',
        minHeight: '200px'
      }}
    >
      {/* Background Pattern */}
      <div className="absolute inset-0 opacity-10">
        <div className="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
        <div className="absolute bottom-0 left-0 w-48 h-48 bg-white rounded-full -ml-24 -mb-24"></div>
      </div>

      {/* Content */}
      <div className="relative h-full p-6 flex flex-col justify-between">
        {/* Top Section */}
        <div className="flex items-start justify-between">
          <div>
            <p className="text-sm text-blue-100 mb-1">موجودی</p>
            <p className="text-3xl font-bold">{formattedBalance} تومان</p>
          </div>
          {/* EMV Chip Icon */}
          <div className="w-[35px] h-[35px] flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" xmlnsXlink="http://www.w3.org/1999/xlink" width="35" height="35" viewBox="0 0 35 35" fill="none">
              <defs>
                <pattern id="chip-pattern-credit-card" patternContentUnits="objectBoundingBox" width="1" height="1">
                  <use xlinkHref="#chip-image-credit-card" transform="scale(0.01)"/>
                </pattern>
                <image id="chip-image-credit-card" width="100" height="100" preserveAspectRatio="none" xlinkHref="data:image/png;base64,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"/>
              </defs>
              <rect width="34.7713" height="34.7713" fill="url(#chip-pattern-credit-card)"/>
            </svg>
          </div>
        </div>

        {/* Middle Section */}
        <div className="flex items-end justify-between mt-auto">
          <div>
            <p className="text-xs text-blue-100 mb-1">صاحب کارت</p>
            <p className="text-lg font-semibold">{cardHolderName}</p>
          </div>
          <div className="text-left">
            <p className="text-xs text-blue-100 mb-1">اعتبار تا</p>
            <p className="text-lg font-semibold">{expirationDate}</p>
          </div>
        </div>

        {/* Bottom Section - Phone Number */}
        <div className="mt-4">
          <div className="flex items-center">
            <span className="text-xl font-mono font-bold tracking-wider">
              {formattedPhoneNumber}
            </span>
          </div>
        </div>

        {/* Mastercard Logo */}
        <div className="absolute bottom-4 left-6 flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" width="44" height="30" viewBox="0 0 44 30" fill="none">
            <circle cx="15" cy="15" r="15" fill="white" fillOpacity="0.5"/>
            <circle cx="29" cy="15" r="15" fill="white" fillOpacity="0.5"/>
          </svg>
        </div>
      </div>
    </div>
  );
};

