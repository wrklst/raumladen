import { Head, Link } from '@inertiajs/react';
import CouponController from '@/actions/App/Http/Controllers/Admin/CouponController';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';
import { index } from '@/routes/admin/coupons';
import type { Paginated } from '@/types';

type CouponRow = {
    id: number;
    code: string;
    type: string;
    value: number;
    min_order_cents: number;
    uses: number;
    max_uses: number | null;
    expires_at: string | null;
    is_active: boolean;
    group: string | null;
};

export default function CouponsIndex({ coupons }: { coupons: Paginated<CouponRow> }) {
    return (
        <>
            <Head title="Coupons" />

            <div className="space-y-6 p-4">
                <Button asChild>
                    <Link href={CouponController.create()}>New coupon</Link>
                </Button>

                {coupons.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No coupons yet.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Code</th>
                                <th className="px-3 py-2 text-right font-medium">Discount</th>
                                <th className="px-3 py-2 text-right font-medium">Min. order</th>
                                <th className="px-3 py-2 text-right font-medium">Uses</th>
                                <th className="px-3 py-2 font-medium">Group</th>
                                <th className="px-3 py-2 font-medium">Expires</th>
                                <th className="px-3 py-2 font-medium">Active</th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {coupons.data.map((coupon) => (
                                <tr key={coupon.id} className="border-t">
                                    <td className="px-3 py-2 font-mono font-medium">
                                        {coupon.code}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {coupon.type === 'percent'
                                            ? `${coupon.value} %`
                                            : formatMoney(coupon.value)}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {coupon.min_order_cents > 0
                                            ? formatMoney(coupon.min_order_cents)
                                            : '—'}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {coupon.uses}
                                        {coupon.max_uses !== null && ` / ${coupon.max_uses}`}
                                    </td>
                                    <td className="px-3 py-2">{coupon.group ?? 'All'}</td>
                                    <td className="px-3 py-2">{coupon.expires_at ?? '—'}</td>
                                    <td className="px-3 py-2">
                                        <Badge variant={coupon.is_active ? 'default' : 'outline'}>
                                            {coupon.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <Link
                                            href={CouponController.edit(coupon.id)}
                                            className="underline underline-offset-4"
                                        >
                                            Edit
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                <Pagination links={coupons.links} />
            </div>
        </>
    );
}

CouponsIndex.layout = {
    breadcrumbs: [{ title: 'Coupons', href: index() }],
};
