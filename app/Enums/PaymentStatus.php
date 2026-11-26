<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    /**
     * The customer has done their part and the provider has not said yes or no yet.
     */
    public function isUnderway(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Nothing more will come of this attempt.
     */
    public function hasFailed(): bool
    {
        return in_array($this, [self::Failed, self::Cancelled, self::Expired], true);
    }
}
