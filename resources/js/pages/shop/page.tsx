export default function Page({
    page,
}: {
    page: { slug: string; title: string; html: string };
}) {
    return (
        <article className="mx-auto max-w-3xl space-y-6">
            <h1 className="text-3xl font-semibold tracking-tight">
                {page.title}
            </h1>
            {/* Markdown is rendered server-side with HTML input stripped. */}
            <div
                className="prose prose-neutral max-w-none dark:prose-invert"
                dangerouslySetInnerHTML={{ __html: page.html }}
            />
        </article>
    );
}
