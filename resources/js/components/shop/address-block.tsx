import type { Address } from '@/types';

export function AddressBlock({
    address,
    countryName,
}: {
    address: Address;
    countryName?: string;
}) {
    return (
        <address className="text-sm not-italic">
            {address.company && <div>{address.company}</div>}
            <div>
                {address.first_name} {address.last_name}
            </div>
            <div>{address.address1}</div>
            {address.address2 && <div>{address.address2}</div>}
            <div>
                {address.zip} {address.city}
            </div>
            <div>{countryName ?? address.country}</div>
        </address>
    );
}
