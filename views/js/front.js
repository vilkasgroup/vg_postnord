 $(document).ready(function () {

    const dummyPoint = `
                <div class="vg_postnord_pickupPoint col-md-4">
                  <img class="logo" src="/modules/vg_postnord/views/img/carrier_image.jpg">
                  <p class="data">TODO</p>
                </div>
                `;

    // dynamic selector for some other modules that show the basket differently
    $('body').on('click', '.vg_postnord_pickupselection_container button.vg_postnord_searchbutton', function (e) {
        e.preventDefault();

        const $button = $(this);
        const $container = $button.parents('.vg_postnord_pickupselection_container');
        const $resultsDiv = $container.find('.vg_postnord_pickup_search_results');
        const id_carrier = $container.data('carrierid');
        const actionurl = $container.data('searchurl');

        // inject three dummy points to show searching status
        $button.prop('disabled', true);
        $resultsDiv.empty();
        $resultsDiv.addClass('vg_postnord_loading');
        for (i = 0; i < 3; i++) {
            $resultsDiv.append(dummyPoint);
        }

        // build our data for the request
        let data = {
            'action': 'search',
            'id_carrier': id_carrier,
            'zipcode': $container.find('.vg_postnord_zipcode').val(),
        }

        // request the pickup points
        $.ajax({
            type: "GET",
            url: actionurl,
            data: data
        }).done(function (resp) {
            // show the results
            $resultsDiv.html(resp);
            // and now that we have results, select the first one
            $resultsDiv.find('.pickupPoint').first().click();
        }).fail(function (jqXHR, textStatus) {
            console.error(jqXHR);
            $resultsDiv.html('<h3 class="alert alert-warning">'+jqXHR.statusText+'</h3>');
        }).always(function() {
            $resultsDiv.removeClass('vg_postnord_loading');
            $button.prop('disabled', false);
        });

    });

    // on page load trigger search to prefill the results
    $(".vg_postnord_pickupselection_container:visible button.vg_postnord_searchbutton").click();

    // when carrier changes if we have our search then click it to prefill if
    // there are no results yet
    $('body').on('change', '.delivery-option input[type=radio]', function (e) {
        // the radio value is actually id_carrier
        const id_carrier = parseInt($(this).val());
        $('.vg_postnord_pickupselection_container[data-carrierid="'+id_carrier+'"] button.vg_postnord_searchbutton').click();
    });

    // the checkout module does not have the searchbutton in a similar way, trigger when it looks like its done
    // it does not seem to have any ready event but lets tag along to its ajax and check from there
    if ($('body#module-thecheckout-order').length) {
        $(document).ajaxComplete(function (event, xhr, settings) {
            if (xhr.responseJSON && xhr.responseJSON.shippingBlock) {
                setTimeout(function () {
                    console.debug('shipping updated by the checkout, triggering postnord pickuplocation search if required');
                    $(".vg_postnord_pickupselection_container:visible button.vg_postnord_searchbutton").click();
                }, 50)
            }
        });
    }
});

function storePickupPoint(element, selectedpickupid, carrieridreference, url) {
    // clear selected class
    $(element).parent('.pickupPoints').find('.pickupPoint').removeClass('selected');

    $.ajax({
        type: "POST",
        url: url,
        data: {
            action: 'savepickup',
            pickupCode: selectedpickupid,
            carrierIdReference: carrieridreference,
        }
    }).done(function (resp) {
        // highlight our selected
        $(element).addClass('selected');
        $("#pickupResultMsg").html(resp.responseText);
    }).fail(function (jqXHR, textStatus) {
        console.error(jqXHR);
        alert("Failed saving pickup location: " + textStatus);
    });

}
