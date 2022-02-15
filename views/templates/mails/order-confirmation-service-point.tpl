{if service_point}
<div class="vg-postnord-order-confirmation-service-point">
  <p>
    <span style="font-weight:bold">{$service_point_header}</span><br>
    {assign var="delivery_address" value=$service_point['deliveryAddress']}
    {$service_point['name']}<br>
    {$delivery_address['streetName']} {$delivery_address['streetNumber']}<br>
    {$delivery_address['postalCode']} {$delivery_address['city']}
  </p>
</div>
{/if}
