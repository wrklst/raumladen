import { useTrans } from '@/hooks/use-translation';

/**
 * Translated labels for the enum values the backend sends as raw strings.
 * Unknown values are shown as they are.
 */
export function useLabels() {
    const { t } = useTrans();

    const pick = (labels: Record<string, string>) => (value: string) =>
        labels[value] ?? value;

    return {
        orderStatus: pick({
            pending: t('Awaiting payment'),
            paid: t('Paid'),
            shipped: t('Shipped'),
            cancelled: t('Cancelled'),
            refunded: t('Refunded'),
        }),
        paymentStatus: pick({
            open: t('Open'),
            pending: t('Pending'),
            paid: t('Paid'),
            failed: t('Failed'),
            cancelled: t('Cancelled'),
            expired: t('Expired'),
            refunded: t('Refunded'),
        }),
        invoiceType: pick({
            invoice: t('Invoice'),
            credit_note: t('Credit note'),
        }),
        taxTreatment: pick({
            domestic: t('Domestic'),
            distance_home: t('EU distance sale (home rate)'),
            oss: t('OSS (destination rate)'),
            intra_community: t('Intra-community supply'),
            reverse_charge: t('Reverse charge'),
            export: t('Export'),
            small_business: t('Small business (§ 19 UStG)'),
        }),
        couponType: pick({
            percent: t('Percent'),
            fixed: t('Fixed amount'),
        }),
        productType: pick({
            book: t('Book'),
        }),
        taxClass: pick({
            standard: t('Standard rate'),
            reduced: t('Reduced rate'),
            zero: t('Zero rate'),
        }),
    };
}
