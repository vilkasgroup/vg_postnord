<div class="vg_postnord_pickupselection_container block container-fluid"
    id="vg_postnord_carrier_pickup_selector_{$vg_postnord_carrier_id}"
    data-searchurl="{$vg_postnord_search_pickuppoints_action}" data-carrierid="{$vg_postnord_carrier_id}">
    <div class="vg_postnord_pickup_search_results row">
    </div>
    <div class="vg_postnord_pickup_search_container row form-group">
        <div class="col-md-9">
            <input type="hidden" class="carrierIdReference" value="{$vg_postnord_carrier_reference}" />
            <input type="text" class="vg_postnord_zipcode form-control col-md-3"
                value="{$vg_postnord_postcode_prefill}">
        </div>
        <div class="col-md-3">
            <button class="vg_postnord_searchbutton btn btn-primary">
                {l s='Search' d='Modules.Vgpostnord.Shop'}
            </button>
        </div>
    </div>
    <div class="row form-group">
        <div class="col-md-12"><hr /></div>
    </div>
</div>