import { Badge } from '@/components/ui/badge';
import { useTrans } from '@/hooks/use-translation';
import type { Order } from '@/types';

const variants: Record<
    Order['status'],
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    pending: 'outline',
    paid: 'default',
    shipped: 'secondary',
    cancelled: 'destructive',
    refunded: 'destructive',
};

const labels: Record<Order['status'], string> = {
    pending: 'Awaiting payment',
    paid: 'Paid',
    shipped: 'Shipped',
    cancelled: 'Cancelled',
    refunded: 'Refunded',
};

export function OrderStatusBadge({ status }: { status: Order['status'] }) {
    const { t } = useTrans();

    return <Badge variant={variants[status]}>{t(labels[status])}</Badge>;
}
