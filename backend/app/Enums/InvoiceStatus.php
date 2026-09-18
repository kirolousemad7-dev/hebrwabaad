<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'DRAFT';
    case Issued = 'ISSUED';
    case Sent = 'SENT';
    case PartiallyPaid = 'PARTIALLY_PAID';
    case Paid = 'PAID';
    case Overdue = 'OVERDUE';
    case Cancelled = 'CANCELLED';
    case Void = 'VOID';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Only drafts may have their lines, totals, or terms rewritten.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Statuses where the invoice is live and may still receive money.
     */
    public function isPayable(): bool
    {
        return in_array($this, [self::Issued, self::Sent, self::PartiallyPaid, self::Overdue], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled, self::Void], true);
    }

    /**
     * Drafts and voided invoices never reach the customer portal.
     */
    public function isVisibleToCustomer(): bool
    {
        return ! in_array($this, [self::Draft, self::Void], true);
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Issued => 'صادرة',
            self::Sent => 'مُرسلة',
            self::PartiallyPaid => 'مدفوعة جزئياً',
            self::Paid => 'مدفوعة',
            self::Overdue => 'متأخرة',
            self::Cancelled => 'ملغاة',
            self::Void => 'ملغاة نهائياً',
        };
    }
}
