/**
 * The small rendition the shop stores beside every picture it made
 * (App\Support\Picture), as a `srcset`; none for a picture stored before that.
 */
export const pictureSrcSet = (url: string): string | undefined =>
    url.endsWith('.webp')
        ? `${url.replace(/\.webp$/, '-600.webp')} 600w, ${url} 2000w`
        : undefined;
