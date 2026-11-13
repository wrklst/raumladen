export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/** Shape of a plain Laravel paginator passed to Inertia. */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: PaginationLink[];
};

/** Shape of a JsonResource::collection() over a paginator. */
export type ResourcePaginated<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        links: PaginationLink[];
    };
};

export type Address = {
    first_name?: string | null;
    last_name?: string | null;
    company?: string | null;
    address1?: string | null;
    address2?: string | null;
    zip?: string | null;
    city?: string | null;
    country?: string | null;
};

export type ProductCard = {
    id: number;
    type: string;
    title: string;
    slug: string;
    url: string;
    price_cents: number;
    prices_include_tax: boolean;
    tax_rate: number;
    currency: string;
    image: string | null;
    in_stock: boolean;
    authors: string | null;
};

export type ProductDetail = ProductCard & {
    sku: string | null;
    description: string | null;
    images: string[];
    specs: Record<string, string>;
    spec_fields: string[];
    stock: number | null;
    weight_grams: number | null;
};

export type CartLine = {
    product_id: number;
    name: string;
    sku: string | null;
    quantity: number;
    unit_price_cents: number;
    tax_class: string;
    tax_rate: number;
    tax_category: string;
    discount_cents: number;
    line_net_cents: number;
    line_tax_cents: number;
    line_gross_cents: number;
    line_display_cents: number;
    image: string | null;
    slug: string | null;
    url: string;
    digital: boolean;
};

export type TaxGroup = {
    rate: number;
    category: string;
    net: number;
    tax: number;
    gross: number;
};

export type CartTotals = {
    lines: CartLine[];
    treatment: string;
    tax_note: string | null;
    prices_include_tax: boolean;
    country: string;
    vat_id_valid: boolean;
    subtotal_display_cents: number;
    discount_cents: number;
    coupon_code: string | null;
    coupon_error: string | null;
    shipping_display_cents: number;
    tax_cents: number;
    total_cents: number;
    tax_summary: TaxGroup[];
};

export type OrderItem = {
    id: number;
    product_id: number | null;
    name: string;
    sku: string | null;
    quantity: number;
    unit_price_cents: number;
    tax_rate: number;
    discount_cents: number;
    line_net_cents: number;
    line_tax_cents: number;
    line_gross_cents: number;
};

export type InvoiceSummary = {
    id: number;
    number: string;
    type: 'invoice' | 'credit_note';
    issued_at: string;
    total_gross_cents: number;
};

export type PaymentSummary = {
    id: number;
    gateway: string;
    gateway_id: string | null;
    status: string;
    method: string | null;
    amount_cents: number;
    refunded_cents: number;
    created_at: string | null;
};

export type Order = {
    id: number;
    uuid: string;
    number: string;
    status: 'pending' | 'paid' | 'shipped' | 'cancelled' | 'refunded';
    locale: string;
    currency: string;
    email: string;
    customer_name: string;
    customer_group_code: string;
    prices_include_tax: boolean;
    billing_address: Address;
    shipping_address: Address;
    country_name: string;
    vat_id: string | null;
    vat_id_valid: boolean;
    tax_treatment: string;
    tax_note: string | null;
    coupon_code: string | null;
    subtotal_net_cents: number;
    discount_cents: number;
    shipping_net_cents: number;
    shipping_tax_cents: number;
    tax_cents: number;
    total_cents: number;
    tax_summary: TaxGroup[];
    payment_gateway: string | null;
    customer_note: string | null;
    created_at: string | null;
    paid_at: string | null;
    shipped_at: string | null;
    cancelled_at: string | null;
    items?: OrderItem[];
    invoices?: InvoiceSummary[];
    payments?: PaymentSummary[];
};

export type Seo = {
    title: string;
    description: string;
    canonical: string;
    alternates: Record<string, string>;
    image: string | null;
    type: string;
    locale: string;
    index: boolean;
};

export type FooterPage = {
    slug: string;
    title: string;
};

export type ShopSharedProps = {
    locale: string;
    locales: string[];
    translations: Record<string, string>;
    shop: { name: string; currency: string };
    cart_count: number;
    footer_pages: FooterPage[];
    seo?: Seo;
    auth: { user: { id: number; name: string; email: string; is_admin: boolean } | null };
};
