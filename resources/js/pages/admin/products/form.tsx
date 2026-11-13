import { Form, Head } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/admin/products';

type Product = {
    id: number;
    type: string;
    sku: string | null;
    slug: Record<string, string>;
    title: Record<string, string>;
    description: Record<string, string>;
    tax_class: string;
    price: string;
    stock: number;
    track_stock: boolean;
    weight_grams: number | null;
    images: { path: string; url: string }[];
    specs: Record<string, string>;
    is_active: boolean;
    published_at: string | null;
    group_prices: Record<string, string>;
};

type Props = {
    product: Product | null;
    locales: string[];
    groups: { id: number; name: string; code: string; is_default: boolean }[];
    types: { value: string; fields: Record<string, string> }[];
    tax_classes: string[];
};

const textareaClass =
    'border-input bg-background flex min-h-24 w-full rounded-md border px-3 py-2 text-sm shadow-xs';

function Field({
    label,
    htmlFor,
    error,
    children,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

export default function ProductForm({
    product,
    locales,
    groups,
    types,
    tax_classes,
}: Props) {
    const [type, setType] = useState(product?.type ?? types[0]?.value ?? '');
    const specFields = types.find((t) => t.value === type)?.fields ?? {};
    const customGroups = groups.filter((group) => !group.is_default);
    const title = product ? (product.title[locales[0]] ?? 'Edit product') : 'New product';

    return (
        <>
            <Head title={title} />

            <div className="max-w-3xl space-y-6 p-4">
                <Form
                    {...(product
                        ? ProductController.update.form({ product: product.id })
                        : ProductController.store.form())}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Basics</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Type" error={errors.type}>
                                        <Select
                                            name="type"
                                            value={type}
                                            onValueChange={setType}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {types.map((t) => (
                                                    <SelectItem key={t.value} value={t.value}>
                                                        {t.value}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>

                                    <Field label="SKU" htmlFor="sku" error={errors.sku}>
                                        <Input
                                            id="sku"
                                            name="sku"
                                            defaultValue={product?.sku ?? ''}
                                        />
                                    </Field>

                                    <Field label="Tax class" error={errors.tax_class}>
                                        <Select
                                            name="tax_class"
                                            defaultValue={product?.tax_class ?? tax_classes[0]}
                                        >
                                            <SelectTrigger className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {tax_classes.map((taxClass) => (
                                                    <SelectItem key={taxClass} value={taxClass}>
                                                        {taxClass}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>

                                    <Field
                                        label="Price (gross, EUR)"
                                        htmlFor="price"
                                        error={errors.price}
                                    >
                                        <Input
                                            id="price"
                                            name="price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                            defaultValue={product?.price ?? ''}
                                        />
                                    </Field>

                                    <Field label="Stock" htmlFor="stock" error={errors.stock}>
                                        <Input
                                            id="stock"
                                            name="stock"
                                            type="number"
                                            min="0"
                                            required
                                            defaultValue={product?.stock ?? 0}
                                        />
                                    </Field>

                                    <Field
                                        label="Weight (grams)"
                                        htmlFor="weight_grams"
                                        error={errors.weight_grams}
                                    >
                                        <Input
                                            id="weight_grams"
                                            name="weight_grams"
                                            type="number"
                                            min="0"
                                            defaultValue={product?.weight_grams ?? ''}
                                        />
                                    </Field>

                                    <Field
                                        label="Published at"
                                        htmlFor="published_at"
                                        error={errors.published_at}
                                    >
                                        <Input
                                            id="published_at"
                                            name="published_at"
                                            type="date"
                                            defaultValue={product?.published_at ?? ''}
                                        />
                                    </Field>

                                    <div className="flex flex-col justify-end gap-3">
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                name="track_stock"
                                                value="1"
                                                defaultChecked={product?.track_stock ?? true}
                                            />
                                            Track stock
                                        </label>
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                name="is_active"
                                                value="1"
                                                defaultChecked={product?.is_active ?? true}
                                            />
                                            Active
                                        </label>
                                    </div>
                                </CardContent>
                            </Card>

                            {locales.map((locale, i) => (
                                <Card key={locale}>
                                    <CardHeader>
                                        <CardTitle>
                                            Translation: {locale.toUpperCase()}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        <Field
                                            label="Title"
                                            htmlFor={`title_${locale}`}
                                            error={errors[`title.${locale}`]}
                                        >
                                            <Input
                                                id={`title_${locale}`}
                                                name={`title[${locale}]`}
                                                required={i === 0}
                                                defaultValue={product?.title[locale] ?? ''}
                                            />
                                        </Field>
                                        <Field
                                            label="Slug"
                                            htmlFor={`slug_${locale}`}
                                            error={errors[`slug.${locale}`]}
                                        >
                                            <Input
                                                id={`slug_${locale}`}
                                                name={`slug[${locale}]`}
                                                placeholder="auto from title"
                                                defaultValue={product?.slug[locale] ?? ''}
                                            />
                                        </Field>
                                        <Field
                                            label="Description"
                                            htmlFor={`description_${locale}`}
                                            error={errors[`description.${locale}`]}
                                        >
                                            <textarea
                                                id={`description_${locale}`}
                                                name={`description[${locale}]`}
                                                className={textareaClass}
                                                defaultValue={
                                                    product?.description[locale] ?? ''
                                                }
                                            />
                                        </Field>
                                    </CardContent>
                                </Card>
                            ))}

                            {Object.keys(specFields).length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Specs</CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 sm:grid-cols-2">
                                        {Object.entries(specFields).map(
                                            ([field, inputType]) => (
                                                <Field
                                                    key={field}
                                                    label={field}
                                                    htmlFor={`spec_${field}`}
                                                    error={errors[`specs.${field}`]}
                                                >
                                                    <Input
                                                        id={`spec_${field}`}
                                                        name={`specs[${field}]`}
                                                        type={inputType}
                                                        defaultValue={
                                                            product?.specs[field] ?? ''
                                                        }
                                                    />
                                                </Field>
                                            ),
                                        )}
                                    </CardContent>
                                </Card>
                            )}

                            {customGroups.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Group prices</CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4 sm:grid-cols-2">
                                        {customGroups.map((group) => (
                                            <Field
                                                key={group.id}
                                                label={`${group.name} price (gross, EUR)`}
                                                htmlFor={`group_price_${group.id}`}
                                                error={errors[`group_prices.${group.id}`]}
                                            >
                                                <Input
                                                    id={`group_price_${group.id}`}
                                                    name={`group_prices[${group.id}]`}
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    defaultValue={
                                                        product?.group_prices[group.id] ?? ''
                                                    }
                                                />
                                            </Field>
                                        ))}
                                    </CardContent>
                                </Card>
                            )}

                            <Card>
                                <CardHeader>
                                    <CardTitle>Images</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    {product && product.images.length > 0 && (
                                        <ul className="flex flex-wrap gap-4">
                                            {product.images.map((image) => (
                                                <li
                                                    key={image.path}
                                                    className="flex flex-col items-center gap-2"
                                                >
                                                    <img
                                                        src={image.url}
                                                        alt=""
                                                        className="size-24 rounded border object-cover"
                                                    />
                                                    <label className="flex items-center gap-2 text-xs">
                                                        <Checkbox
                                                            name="existing_images[]"
                                                            value={image.path}
                                                            defaultChecked
                                                        />
                                                        Keep
                                                    </label>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                    <Field
                                        label="Add images"
                                        htmlFor="images"
                                        error={errors.images ?? errors['images.0']}
                                    >
                                        <Input
                                            id="images"
                                            name="images[]"
                                            type="file"
                                            multiple
                                            accept="image/*"
                                        />
                                    </Field>
                                </CardContent>
                            </Card>

                            <Button disabled={processing}>Save</Button>
                        </>
                    )}
                </Form>

                {product && (
                    <Form
                        {...ProductController.destroy.form({ product: product.id })}
                        onBefore={() => confirm('Really delete this product?')}
                    >
                        <Button type="submit" variant="destructive">
                            Delete product
                        </Button>
                    </Form>
                )}
            </div>
        </>
    );
}

ProductForm.layout = ({ product, locales }: Props) => ({
    breadcrumbs: [
        { title: 'Products', href: index() },
        product
            ? {
                  title: product.title[locales[0]] ?? 'Edit product',
                  href: ProductController.edit({ product: product.id }),
              }
            : { title: 'New product', href: ProductController.create() },
    ],
});
