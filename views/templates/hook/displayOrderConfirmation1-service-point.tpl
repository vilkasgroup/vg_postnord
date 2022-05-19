{if service_point}
<section id="vg-postnord-displayorderconfirmation-service-point">
    <article id="pickup-address" class="address">
        <p class="address-header"><strong>{$service_point_header}</strong></p>
        {assign var="pickup_address" value=$service_point['deliveryAddress']}
        <address class="address-body">
        {$service_point['name']}<br />
        {$pickup_address['streetName']} {$pickup_address['streetNumber']}<br />
        {$pickup_address['postalCode']} {$pickup_address['city']}
        </address>
    </article>
</section>
{/if}
