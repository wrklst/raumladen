import type { SVGAttributes } from 'react';

/**
 * The Space Issues mark: the initials, set in the current text colour.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 40 40"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <text
                x="20"
                y="29.5"
                textAnchor="middle"
                fontSize="26"
                fontWeight="700"
                fontFamily="Manrope, ui-sans-serif, system-ui, sans-serif"
            >
                SI
            </text>
        </svg>
    );
}
