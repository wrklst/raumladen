import type { SVGAttributes } from 'react';

/**
 * The shop's default mark, an open book, in the current text colour.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 40 40"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path d="M5 9h13v22H5zM22 9h13v22H22z" />
        </svg>
    );
}
