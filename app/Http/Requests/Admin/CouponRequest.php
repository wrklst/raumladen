<?php

namespace App\Http\Requests\Admin;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CustomerGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CouponRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $coupon = $this->route('coupon');

        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique(Coupon::class)->ignore($coupon instanceof Coupon ? $coupon->id : null)],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => ['required', 'numeric', 'min:0', Rule::when($this->input('type') === CouponType::Percent->value, ['max:100'])],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'customer_group_id' => ['nullable', Rule::exists(CustomerGroup::class, 'id')],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function couponAttributes(): array
    {
        $type = CouponType::from($this->validated('type'));

        return [
            'code' => mb_strtoupper($this->validated('code')),
            'type' => $type,
            'value' => $type === CouponType::Fixed
                ? (int) round((float) $this->validated('value') * 100)
                : (int) round((float) $this->validated('value')),
            'min_order_cents' => (int) round((float) ($this->validated('min_order') ?? 0) * 100),
            'starts_at' => $this->validated('starts_at'),
            'expires_at' => $this->validated('expires_at') ? now()->parse($this->validated('expires_at'))->endOfDay() : null,
            'max_uses' => $this->validated('max_uses'),
            'customer_group_id' => $this->validated('customer_group_id'),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
