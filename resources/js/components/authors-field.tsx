import { X } from 'lucide-react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/hooks/use-translation';

type Author = { id: number; name: string };

/**
 * The authors of a book: entries from Catalogue → Authors, linked in the
 * order they are named on the book. One or several; a name the shop does not
 * know yet can be typed in and becomes an entry when the book is saved.
 */
export function AuthorsField({
    id,
    authors,
    defaultValue,
}: {
    id: string;
    authors: Author[];
    defaultValue: number[];
}) {
    const { t } = useTrans();
    const [chosen, setChosen] = useState<number[]>(defaultValue);
    const byId = new Map(authors.map((author) => [author.id, author]));
    const open = authors.filter((author) => !chosen.includes(author.id));

    return (
        <div id={id} className="grid gap-2">
            {chosen.length > 0 && (
                <ol className="flex flex-wrap gap-2">
                    {chosen.map((authorId) => (
                        <li
                            key={authorId}
                            className="flex items-center gap-1 rounded-md border px-2 py-1 text-sm"
                        >
                            <input
                                type="hidden"
                                name="author_ids[]"
                                value={authorId}
                            />
                            {byId.get(authorId)?.name}
                            <button
                                type="button"
                                aria-label={t('Remove :name', {
                                    name: byId.get(authorId)?.name ?? '',
                                })}
                                onClick={() =>
                                    setChosen(
                                        chosen.filter(
                                            (other) => other !== authorId,
                                        ),
                                    )
                                }
                                className="rounded p-0.5 hover:bg-accent hover:text-accent-foreground"
                            >
                                <X className="size-3.5" />
                            </button>
                        </li>
                    ))}
                </ol>
            )}
            <div className="grid gap-2 sm:grid-cols-2">
                <select
                    aria-label={t('Add an author')}
                    value=""
                    onChange={(event) =>
                        setChosen([...chosen, Number(event.target.value)])
                    }
                    disabled={open.length === 0}
                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="">{t('Add an author')}</option>
                    {open.map((author) => (
                        <option key={author.id} value={author.id}>
                            {author.name}
                        </option>
                    ))}
                </select>
                <Input
                    name="new_authors"
                    aria-label={t('New authors, separated by commas')}
                    placeholder={t('New authors, separated by commas')}
                />
            </div>
        </div>
    );
}
