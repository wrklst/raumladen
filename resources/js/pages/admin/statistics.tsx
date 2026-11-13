import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTrans } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format';
import { statistics } from '@/routes/admin';

type Row = { name: string; count: number };

type Props = {
    days: number;
    enabled: boolean;
    totals: {
        visits: number;
        visitors: number;
        pageviews: number;
        seconds_per_visit: number;
    };
    by_day: { day: string; visits: number; pageviews: number }[];
    pages: Row[];
    referrers: Row[];
    campaigns: Row[];
    landing_pages: Row[];
    devices: Row[];
    locales: Row[];
    funnel: {
        visits: number;
        product: number;
        cart: number;
        checkout: number;
        orders: number;
    };
};

/** A ranked list with a bar per row; the numbers are in the table, the bars only help the eye. */
function Ranking({
    title,
    rows,
    empty,
}: {
    title: string;
    rows: Row[];
    empty: string;
}) {
    const max = Math.max(1, ...rows.map((row) => row.count));

    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent>
                {rows.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{empty}</p>
                ) : (
                    <table className="w-full text-sm">
                        <caption className="sr-only">{title}</caption>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.name}>
                                    <th
                                        scope="row"
                                        className="max-w-0 truncate py-1 pr-3 text-left font-normal"
                                    >
                                        {row.name}
                                    </th>
                                    <td
                                        className="w-1/3 py-1"
                                        aria-hidden="true"
                                    >
                                        <div
                                            className="h-2 rounded-sm bg-primary"
                                            style={{
                                                width: `${(row.count / max) * 100}%`,
                                            }}
                                        />
                                    </td>
                                    <td className="w-12 py-1 pl-3 text-right tabular-nums">
                                        {row.count}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </CardContent>
        </Card>
    );
}

export default function Statistics(props: Props) {
    const { t, locale } = useTrans();
    const { totals, funnel } = props;
    const noData = t('No visits in this period.');
    const peak = Math.max(1, ...props.by_day.map((day) => day.visits));
    const minutes = `${Math.floor(totals.seconds_per_visit / 60)}:${String(totals.seconds_per_visit % 60).padStart(2, '0')}`;

    const cards: [string, string | number][] = [
        [t('Visits'), totals.visits],
        [t('Visitors per day (sum)'), totals.visitors],
        [t('Page views'), totals.pageviews],
        [t('Time per visit (min)'), minutes],
    ];

    const steps: [string, number][] = [
        [t('Visits'), funnel.visits],
        [t('Saw a book'), funnel.product],
        [t('Opened the cart'), funnel.cart],
        [t('Reached checkout'), funnel.checkout],
        [t('Orders'), funnel.orders],
    ];

    return (
        <>
            <Head title={t('Statistics')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        {props.enabled
                            ? t(
                                  'Counted anonymously and without cookies. Visitors who send Do Not Track are not included, so real numbers are somewhat higher.',
                              )
                            : t(
                                  'Counting is switched off under Shop settings, Channels and search engines.',
                              )}
                    </p>
                    <nav
                        aria-label={t('Period')}
                        className="flex gap-1 text-sm"
                    >
                        {[7, 30, 90, 365].map((days) => (
                            <Link
                                key={days}
                                href={statistics({ query: { days } })}
                                aria-current={
                                    days === props.days ? 'true' : undefined
                                }
                                className={
                                    days === props.days
                                        ? 'rounded-md bg-primary px-3 py-1 text-primary-foreground'
                                        : 'rounded-md border px-3 py-1'
                                }
                            >
                                {t(':days days', { days })}
                            </Link>
                        ))}
                    </nav>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {cards.map(([label, value]) => (
                        <Card key={label}>
                            <CardHeader>
                                <CardTitle className="text-sm font-normal text-muted-foreground">
                                    {label}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-2xl font-semibold tabular-nums">
                                {value}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Visits per day')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {/* Bars for the eye; the same numbers follow as a table for everyone else. */}
                        <div
                            className="flex h-32 items-end gap-px"
                            aria-hidden="true"
                        >
                            {props.by_day.map((day) => (
                                <div
                                    key={day.day}
                                    title={`${formatDate(day.day, locale)}: ${day.visits}`}
                                    className="min-w-0 flex-1 bg-primary"
                                    style={{
                                        height: `${Math.max(day.visits > 0 ? 3 : 0, (day.visits / peak) * 100)}%`,
                                    }}
                                />
                            ))}
                        </div>
                        <details className="mt-3 text-sm">
                            <summary className="cursor-pointer underline underline-offset-4">
                                {t('Show as table')}
                            </summary>
                            <table className="mt-2 w-full max-w-md">
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="py-1 font-medium"
                                        >
                                            {t('Day')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-1 text-right font-medium"
                                        >
                                            {t('Visits')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="py-1 text-right font-medium"
                                        >
                                            {t('Page views')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {props.by_day.map((day) => (
                                        <tr key={day.day} className="border-t">
                                            <td className="py-1">
                                                {formatDate(day.day, locale)}
                                            </td>
                                            <td className="py-1 text-right tabular-nums">
                                                {day.visits}
                                            </td>
                                            <td className="py-1 text-right tabular-nums">
                                                {day.pageviews}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </details>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('From visit to order')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ol className="grid gap-3 text-sm sm:grid-cols-5">
                            {steps.map(([label, count]) => (
                                <li
                                    key={label}
                                    className="rounded-md border p-3"
                                >
                                    <div className="text-muted-foreground">
                                        {label}
                                    </div>
                                    <div className="text-xl font-semibold tabular-nums">
                                        {count}
                                    </div>
                                    {funnel.visits > 0 && (
                                        <div className="text-xs text-muted-foreground tabular-nums">
                                            {Math.round(
                                                (count / funnel.visits) * 100,
                                            )}{' '}
                                            %
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Ranking
                        title={t('Pages')}
                        rows={props.pages}
                        empty={noData}
                    />
                    <Ranking
                        title={t('Landing pages')}
                        rows={props.landing_pages}
                        empty={noData}
                    />
                    <Ranking
                        title={t('Referring sites')}
                        rows={props.referrers}
                        empty={noData}
                    />
                    <Ranking
                        title={t('Campaigns')}
                        rows={props.campaigns}
                        empty={noData}
                    />
                    <Ranking
                        title={t('Devices')}
                        rows={props.devices}
                        empty={noData}
                    />
                    <Ranking
                        title={t('Languages')}
                        rows={props.locales}
                        empty={noData}
                    />
                </div>
            </div>
        </>
    );
}

Statistics.layout = {
    breadcrumbs: [{ title: 'Statistics', href: statistics() }],
};
