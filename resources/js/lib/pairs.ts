/**
 * Label and content groups kept as one text, a line each: "Label: content".
 * A line without a label is content alone. Credits are stored this way, so
 * a catalogue file can carry them as plain text.
 */
export type Pair = { label: string; content: string };

const none = '';

/** A new, empty group. */
export const emptyPair = (): Pair => ({ label: none, content: none });

export function parsePairs(text: string | null | undefined): Pair[] {
    return (text ?? '')
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line !== '')
        .map((line) => {
            const at = line.indexOf(': ');

            return at === -1
                ? { label: none, content: line }
                : { label: line.slice(0, at), content: line.slice(at + 2) };
        });
}

export function joinPairs(pairs: Pair[]): string {
    return pairs
        .map(({ label, content }) => ({
            // A label never carries the separator or a line break itself.
            label: label
                .replace(/[\r\n]+/g, ' ')
                .replace(/: /g, ' ')
                .trim(),
            content: content.replace(/[\r\n]+/g, ' ').trim(),
        }))
        .filter(({ content }) => content !== '')
        .map(({ label, content }) =>
            label === '' ? content : `${label}: ${content}`,
        )
        .join('\n');
}
