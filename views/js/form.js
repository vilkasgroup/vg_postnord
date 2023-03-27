$(document).ready(function () {
    const $servicePointTable = $('body').find('.vg-postnord-service-point-id-picker');
    const $tableBody = $servicePointTable.find('tbody');
    const servicePointValue = $('#vg_postnord_booking_servicepointid_value').val();
    const ajaxurl = $('#vg_postnord_edit_booking').data('ajaxurl');
    const idOrder = $('#vg_postnord_booking_id_order').val();
    $servicePointTable.find('thead').remove()
    
    // dynamic selector for some other modules that show the basket differently
    $('body').on('click', '#vg_postnord_booking_button', function (e) {
        e.preventDefault();
        
        const $button = $(this).find('button');
        const $buttonText = $button.html()
        const zipcode = $('#vg_postnord_booking_postcode').val();

        $button.prop('disabled', true);
        $('#save-button').prop('disabled', true);

        $button.html(`  
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span class="sr-only">Loading...</span>
        `)
        // request the pickup points
        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                idOrder,
                zipcode
            },
        }).done(function (resp) {
            // render the results
            try {
                let servicePoint = resp

                if (typeof resp === 'string') {
                    servicePoint = JSON.parse(resp)
                }

                if (Array.isArray(servicePoint)) {
                    $tableBody.empty();
                    servicePoint.forEach((element, i) => {
                        $tableBody.append(renderPickupPoint(element, i, servicePointValue))
                    });
                }
            } catch (error) {
                $tableBody.html(`<h3 class="alert alert-warning"> Error while fetching data. </h3>`);
                console.error(error)
                console.error(resp)
            }
            $('#save-button').prop('disabled', false);
        }).fail(function (jqXHR, textStatus) {
            console.error(jqXHR);

            const error = jqXHR.responseJSON
            if (error.error) {
                $tableBody.html('<h3 class="alert alert-warning">' + error.error + '</h3>');
            } else {
                $tableBody.html('<h3 class="alert alert-warning">' + jqXHR.statusText + '</h3>');
            }
        }).always(function () {
            $button.prop('disabled', false);
            $button.html($buttonText)
            $('#vg_postnord_booking').find('.d-none').removeClass('d-none')
        });

    });

    /**
     * Render one pickup point as html. data from pickupoint api
     */
    function renderPickupPoint($servicePoint, $i, servicePointValue) {
        console.debug($servicePoint);
        const { servicePointId, servicePointDetail } = $servicePoint
        let html = `
        <tr><td>
            <div class="form-check form-check-radio form-radio">
                <label class="form-check-label">
                    <input type="radio" id="vg_postnord_booking_servicepointid_${$i}" 
                    name="vg_postnord_booking[servicepointid]" 
                    required="required" class="form-check-input" value=${servicePointId}
                    ${servicePointId == servicePointValue ? 'checked' : ''}>
                    <i class="form-check-round"></i>
                    ${servicePointDetail}
                </label>
            </div>
        </td></tr>`;

        return html;
    }

    // trigger search when changing service point to prefill the results
    $('body').on('click', '#vg_postnord_booking_change_service_point_button', function (e) {
        e.preventDefault()

        $('#vg_postnord_booking_button_search').click()
        
        $('.vg-postnord-booking-change-service-point-button').remove()
    })
    
    if(!servicePointValue){
        $('#vg_postnord_booking_change_service_point_button').click()
    }
});
