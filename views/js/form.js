$(document).ready(function () {

    // dynamic selector for some other modules that show the basket differently
    $('body').on('click', '#vg_postnord_booking_button', function (e) {
        e.preventDefault();

        const $servicePointTable = $('body').find('.servicePointIdPicker');
        const $tableBody = $servicePointTable.find('tbody');
        const zipcode = $('#vg_postnord_booking_postcode').val();
        const ajaxurl = $('#vg_postnord_edit_booking').data('ajaxurl');
        const idOrder = $('#vg_postnord_booking_id_order').val();

        // request the pickup points
        $.ajax({
            type: "POST",
            url: ajaxurl,
            data: {
                idOrder,
                zipcode
            }
        }).done(function (resp) {
            // render the results
            const servicePoint = JSON.parse(resp)
            if (servicePoint) {
                $tableBody.empty();
                servicePoint.forEach((element, i) => {
                    $tableBody.append(renderPickupPoint(element, i))
                }
                );
            }
        }).fail(function (jqXHR, textStatus) {
            console.error(jqXHR);
            $tableBody.html('<h3 class="alert alert-warning">' + jqXHR.statusText + '</h3>');
        }).always(function () {
            $tableBody.removeClass('vg_postnord_loading');
            // $button.prop('disabled', false);
        });

    });

    /**
     * Render one pickup point as html. data from pickupoint api
     */
    function renderPickupPoint($servicePoint, $i) {
        console.debug($servicePoint);
        const { servicePointId, servicePointDetail } = $servicePoint
        let html = `
        <tr><td>
            <div class="form-check form-check-radio form-radio">
                <label class="form-check-label">
                    <input type="radio" id="vg_postnord_booking_servicepointid_${$i}" 
                    name="vg_postnord_booking[servicepointid]" 
                    required="required" class="form-check-input" value=${servicePointId}>
                    <i class="form-check-round"></i>
                    ${servicePointDetail}
                </label>
            </div>
        </td></tr>`;

        return html;
    }
});
