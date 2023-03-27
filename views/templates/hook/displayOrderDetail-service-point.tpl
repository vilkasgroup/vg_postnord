{if service_point}
  <div class="col-lg-6 col-xl-4 mb-3 mb-md-0" id="vg-postnord-displayorderdetail-service-point">
    <article id="pickup-address" class="address">
      <p class="address-header"><strong>{$service_point_header}</strong></p>
      {assign var="pickup_address" value=$service_point['deliveryAddress']}
      <address class="address-body">
        {$service_point['name']}<br/>
        {$pickup_address['streetName']} {$pickup_address['streetNumber']}<br/>
        {$pickup_address['postalCode']} {$pickup_address['city']}
      </address>
    </article>
  </div>
  {* and move it next to other addresses *}
  <script type="text/javascript">
    const target = document.querySelector("div.addresses");
    if (target) {
      const source = document.getElementById("vg-postnord-displayorderdetail-service-point");
      target.appendChild(source);
    }
  </script>
{/if}
