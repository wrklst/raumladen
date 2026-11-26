import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/hooks/use-translation';
import { emptyPair, joinPairs, parsePairs } from '@/lib/pairs';
import type { Pair } from '@/lib/pairs';

/**
 * Any number of label and content groups (credits: "Editor" and who it is),
 * added and removed one by one. Sent as one text under `name`, a line per
 * group (see lib/pairs.ts).
 */
export function PairsField({
    id,
    name,
    defaultValue,
}: {
    id: string;
    name: string;
    defaultValue: string;
}) {
    const { t } = useTrans();
    const [pairs, setPairs] = useState<Pair[]>(() => parsePairs(defaultValue));

    const change = (index: number, part: Partial<Pair>): void =>
        setPairs(
            pairs.map((pair, at) =>
                at === index ? { ...pair, ...part } : pair,
            ),
        );

    return (
        <div id={id} className="grid gap-2">
            <input type="hidden" name={name} value={joinPairs(pairs)} />
            {pairs.map((pair, index) => (
                <div
                    key={index}
                    className="grid grid-cols-[minmax(0,1fr)_minmax(0,2fr)_auto] gap-2"
                >
                    <Input
                        aria-label={t('Label')}
                        placeholder={t('Label')}
                        value={pair.label}
                        onChange={(event) =>
                            change(index, { label: event.target.value })
                        }
                    />
                    <Input
                        aria-label={t('Content')}
                        placeholder={t('Content')}
                        value={pair.content}
                        onChange={(event) =>
                            change(index, { content: event.target.value })
                        }
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        aria-label={t('Remove')}
                        onClick={() =>
                            setPairs(pairs.filter((_, at) => at !== index))
                        }
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ))}
            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setPairs([...pairs, emptyPair()])}
                >
                    <Plus className="size-4" />
                    {t('Add')}
                </Button>
            </div>
        </div>
    );
}
