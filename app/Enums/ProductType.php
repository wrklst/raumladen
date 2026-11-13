<?php

namespace App\Enums;

/**
 * Product types. Add a case plus its attribute fields and JSON-LD type to
 * support another kind of product.
 */
enum ProductType: string
{
    case Book = 'book';

    /**
     * Type-specific attributes stored in products.attributes.
     *
     * @return array<string, string> field => input type
     */
    public function attributeFields(): array
    {
        return match ($this) {
            self::Book => [
                'isbn' => 'text',
                'authors' => 'text',
                'publisher' => 'text',
                'pages' => 'number',
                'binding' => 'text',
                'language' => 'text',
                'published' => 'date',
            ],
        };
    }

    /**
     * Whether the product is delivered electronically (service for VAT).
     */
    public function isDigital(): bool
    {
        return match ($this) {
            self::Book => false,
        };
    }

    public function schemaType(): string
    {
        return match ($this) {
            self::Book => 'Book',
        };
    }

    public function googleProductCategory(): string
    {
        return match ($this) {
            self::Book => 'Media > Books',
        };
    }
}
