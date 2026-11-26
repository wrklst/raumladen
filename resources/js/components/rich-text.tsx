import { Bold, Italic, Link2, List, Pilcrow, Heading2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTrans } from '@/hooks/use-translation';

/**
 * A text field that shows its text the way the page will: bold is bold, a
 * list is a list. It sends HTML under `name`; the server cleans it whenever
 * it is shown (App\Support\Html), so what this field produces is not trusted.
 *
 * ponytail: the browser's own editing (contenteditable, execCommand), no
 * editor library. Enough for a few paragraphs with emphasis, links and
 * lists; take a library when tables or images are wanted.
 */
export function RichText({
    id,
    name,
    defaultValue,
    describedBy,
}: {
    id: string;
    name: string;
    defaultValue: string;
    describedBy?: string;
}) {
    const { t } = useTrans();
    const editor = useRef<HTMLDivElement>(null);
    const [html, setHtml] = useState(defaultValue);

    // Set once: React must not rewrite what is being typed into.
    useEffect(() => {
        if (editor.current) {
            editor.current.innerHTML = defaultValue;
        }

        // Paragraphs, not the browser's own line containers.
        document.execCommand('defaultParagraphSeparator', false, 'p');
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const run = (command: string, value?: string): void => {
        editor.current?.focus();
        document.execCommand(command, false, value);
        setHtml(editor.current?.innerHTML ?? '');
    };

    const tools: [string, typeof Bold, () => void][] = [
        [t('Bold'), Bold, () => run('bold')],
        [t('Italic'), Italic, () => run('italic')],
        [t('Heading'), Heading2, () => run('formatBlock', 'h2')],
        [t('Paragraph'), Pilcrow, () => run('formatBlock', 'p')],
        [t('List'), List, () => run('insertUnorderedList')],
        [
            t('Link'),
            Link2,
            () => {
                const address = prompt(t('Address of the link'), 'https://');

                if (address) {
                    run('createLink', address);
                }
            },
        ],
    ];

    return (
        <div className="rounded-md border border-input bg-background">
            <div
                role="toolbar"
                aria-label={t('Formatting')}
                className="flex flex-wrap gap-1 border-b p-1"
            >
                {tools.map(([label, Icon, action]) => (
                    <button
                        key={label}
                        type="button"
                        title={label}
                        aria-label={label}
                        // Keeps the selection in the text while the button is pressed.
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={action}
                        className="rounded p-1.5 hover:bg-accent hover:text-accent-foreground"
                    >
                        <Icon className="size-4" />
                    </button>
                ))}
            </div>
            <div
                ref={editor}
                id={id}
                role="textbox"
                aria-multiline="true"
                aria-describedby={describedBy}
                contentEditable
                suppressContentEditableWarning
                onInput={(event) => setHtml(event.currentTarget.innerHTML)}
                className="prose min-h-32 max-w-none px-3 py-2 text-sm focus:outline-none"
            />
            <input type="hidden" name={name} value={html} />
        </div>
    );
}
