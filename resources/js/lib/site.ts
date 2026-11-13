/**
 * Site name for document titles. The server's value (config app.name, shared
 * as the `name` prop) wins over the one baked in at build time, so a title
 * can never disagree with og:site_name.
 */
let siteName: string = import.meta.env.VITE_APP_NAME || 'Space Issues';

export const setSiteName = (name: string): void => {
    siteName = name;
};

export const pageTitle = (title?: string): string =>
    title ? `${title} – ${siteName}` : siteName;
