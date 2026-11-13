<?php

namespace App\Enums;

/**
 * Who a user is in the shop. Customers buy; everyone else works in the
 * backend, each role in its own areas (see areas()).
 */
enum UserRole: string
{
    /** Everything, including settings and backend users. */
    case Admin = 'admin';

    /** Runs the shop day to day: everything except settings, users and the DATEV export. */
    case Manager = 'manager';

    /** Packs and ships orders. */
    case Fulfilment = 'fulfilment';

    /** Maintains books and content pages. */
    case Editor = 'editor';

    /** Payments, refunds, invoices and the DATEV export. */
    case Accounting = 'accounting';

    case Customer = 'customer';

    /**
     * Backend areas, each guarding a set of routes (routes/admin.php).
     *
     * @var list<string>
     */
    public const array AREAS = ['orders', 'shipping', 'payments', 'catalog', 'content', 'coupons', 'customers', 'invoices', 'datev', 'statistics', 'settings'];

    /**
     * @return list<string>
     */
    public function areas(): array
    {
        return match ($this) {
            self::Admin => self::AREAS,
            self::Manager => ['orders', 'shipping', 'payments', 'catalog', 'content', 'coupons', 'customers', 'invoices', 'statistics'],
            self::Fulfilment => ['orders', 'shipping'],
            self::Editor => ['catalog', 'content'],
            self::Accounting => ['orders', 'payments', 'invoices', 'datev'],
            self::Customer => [],
        };
    }

    public function can(string $area): bool
    {
        return in_array($area, $this->areas(), true);
    }

    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }

    /**
     * Roles that can be given to a backend user.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role) => $role->isStaff()));
    }

    public function label(): string
    {
        return __(match ($this) {
            self::Admin => 'Administrator',
            self::Manager => 'Shop manager',
            self::Fulfilment => 'Fulfilment',
            self::Editor => 'Editor',
            self::Accounting => 'Accounting',
            self::Customer => 'Customer',
        });
    }

    public function description(): string
    {
        return __(match ($this) {
            self::Admin => 'Everything, including settings and backend users.',
            self::Manager => 'Orders, shipping, payments, books, pages, coupons, customers, invoices and statistics.',
            self::Fulfilment => 'Sees orders and marks them as shipped.',
            self::Editor => 'Books and content pages.',
            self::Accounting => 'Sees orders, confirms payments, refunds, invoices and the DATEV export.',
            self::Customer => 'Buys in the shop; no backend access.',
        });
    }
}
