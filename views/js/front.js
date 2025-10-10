$(document).ready(function () {

    /**
     * same format as from the api but minimal data
     */
    const dummyPointData = {
        "name": "Pickuplocation",
        "servicePointId": "0",
        "phoneNoToCashRegister": null,
        "routingCode": "TUR",
        "handlingOffice": null,
        "locationDetail": null,
        "routeDistance": 389,
        "pickup": {
            "cashOnDelivery": null,
            "products": [
                {
                    "name": "Parcel",
                    "timeSlots": {
                        "availableForPickupStandard": [],
                        "availableForPickupEarlyCollect": []
                    }
                }
            ],
            "heavyGoodsProducts": []
        },
        "visitingAddress": {
            "countryCode": "FI",
            "city": "CITY",
            "streetName": "Street",
            "streetNumber": "123",
            "postalCode": "12345",
            "additionalDescription": null
        },
        "deliveryAddress": {
            "countryCode": "FI",
            "city": "CITY",
            "streetName": "Street",
            "streetNumber": "123",
            "postalCode": "12345",
            "additionalDescription": null
        },
        "notificationArea": null,
        "coordinates": [
            {
                "countryCode": "FI",
                "northing": 61.500284,
                "easting": 23.756063,
                "srId": "EPSG:4326"
            }
        ],
        "openingHours": {
            "specialDates": [],
            "postalServices": [
                {
                    "openTime": "07:00",
                    "openDay": "Monday",
                    "closeTime": "21:00",
                    "closeDay": "Monday"
                },
                {
                    "openTime": "07:00",
                    "openDay": "Tuesday",
                    "closeTime": "21:00",
                    "closeDay": "Tuesday"
                },
                {
                    "openTime": "07:00",
                    "openDay": "Wednesday",
                    "closeTime": "21:00",
                    "closeDay": "Wednesday"
                },
                {
                    "openTime": "07:00",
                    "openDay": "Thursday",
                    "closeTime": "21:00",
                    "closeDay": "Thursday"
                },
                {
                    "openTime": "07:00",
                    "openDay": "Friday",
                    "closeTime": "21:00",
                    "closeDay": "Friday"
                },
                {
                    "openTime": "08:00",
                    "openDay": "Saturday",
                    "closeTime": "21:00",
                    "closeDay": "Saturday"
                },
                {
                    "openTime": "11:00",
                    "openDay": "Sunday",
                    "closeTime": "21:00",
                    "closeDay": "Sunday"
                }
            ]
        },
        "type": {
            "groupTypeId": 1,
            "groupTypeName": "Service agent",
            "typeId": 38,
            "typeName": "Hämtställe"
        }
    };

    // dynamic selector for some other modules that show the basket differently
    $('body').on('click', '.vg_postnord_pickupselection_container button.vg_postnord_searchbutton', function (e) {
        e.preventDefault();

        const $button = $(this);
        const $container = $button.parents('.vg_postnord_pickupselection_container');
        const $resultsDiv = $container.find('.vg_postnord_pickup_search_results');
        const actionurl = $container.data('searchurl');

        // inject three dummy points to show searching status
        $button.prop('disabled', true);
        $resultsDiv.empty();
        $resultsDiv.addClass('vg_postnord_loading');
        for (let i = 0; i < 3; i++) {
            let dummyPoint = renderPickupPoint(dummyPointData);
            $resultsDiv.append(dummyPoint);
        }

        // build our data for the request
        let data = {
            'action': 'search',
            'zipcode': $container.find('.vg_postnord_zipcode').val(),
            'carrierIdReference': $container.find('.carrierIdReference').val(),
        }

        // request the pickup points
        $.ajax({
            type: "GET",
            url: actionurl,
            data: data,
            beforeSend: function (xhr) {
                // Disable continue when fetching
                setTimeout(function () { $('button[name="confirmDeliveryOption"]').attr("disabled", true) }, 0)
            }
        }).done(function (resp) {
            // render the results
            $resultsDiv.empty();

            if (resp.servicePoints) {
                resp.servicePoints.forEach((element, i) =>
                    $resultsDiv.append(renderPickupPoint(element, i))
                );

                // resize wrapper if needed (Hummingbird theme)
                resizeCarrierExtraContentWrapper($resultsDiv);

                // and now that we have results rendered, select the first one
                $resultsDiv.find('.vg_postnord_pickupPoint').first().click();
                $('button[name="confirmDeliveryOption"]').attr("disabled", false)
            } else if (resp.error) {
                $resultsDiv.html('<h3 class="alert alert-warning">' + resp.error + '</h3>');
            } else {
                $resultsDiv.html('<h3 class="alert alert-warning">Unknown error, please try again later</h3>');
                console.error(resp);
            }
        }).fail(function (jqXHR, textStatus) {
            console.error(jqXHR);
            $resultsDiv.html('<h3 class="alert alert-warning">' + jqXHR.statusText + '</h3>');
        }).always(function () {
            $resultsDiv.removeClass('vg_postnord_loading');
            $button.prop('disabled', false);
        });

    });

    // on page load trigger search to prefill the results
    $(".vg_postnord_pickupselection_container:visible button.vg_postnord_searchbutton").click();

    // when carrier changes if we have our search then click it to prefill if
    // there are no results yet
    $('body').on('change', '.delivery-option input[type=radio]', function (e) {
        // Enable continue button in case it's disabled by failed postnord
        $('button[name="confirmDeliveryOption"]').attr("disabled", false)
        // the radio value is actually id_carrier
        const id_carrier = parseInt($(this).val());
        $('.vg_postnord_pickupselection_container[data-carrierid="' + id_carrier + '"] button.vg_postnord_searchbutton').click();
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

    /**
     * Render one pickup point as html. data from pickupoint api
     */
    function renderPickupPoint($servicePoint, $i) {
        console.debug($servicePoint);

        let name = $servicePoint.name;
        let servicePointId = $servicePoint.servicePointId;

        let html = `
        <div class="vg_postnord_pickupPoint col-md-4 col-xs-12" onclick="VgPostnordStorePickupPoint(this, '${servicePointId}')">
          <h4 class="vg_postnord_pickup_name">${name}</h4>
          <p class="vg_postnord_pickup_street">${$servicePoint.visitingAddress.streetName} ${$servicePoint.visitingAddress.streetNumber}</p>
          <p class="vg_postnord_pickup_zipcity">${$servicePoint.visitingAddress.postalCode} ${$servicePoint.visitingAddress.city}</p>
        </div>
        `;

        if (($i + 1) % 3 === 0) {
            html += '<div class="clearfix visible-xs-block"></div>'
        }

        return html;
    }

    /**
     * Adjusts the maximum height of the carrier extra content wrapper to fit the height of its inner content.
     *
     * Background: The Hummingbird theme uses Element.clientHeight to determine the height of the carrier extra
     * content wrapper. However, since we show dummy service points before we get a response from the service points
     * API, the max height is set to the height of the dummy data. Thus, we need to resize the wrapper to fit the
     * actual content; otherwise most of the content will be hidden.
     *
     * @param {jQuery} $searchResultsContainer - jQuery object for the search results container (i.e., the inner content).
     */
    function resizeCarrierExtraContentWrapper($searchResultsContainer) {
      const extraContentWrapper = $searchResultsContainer.closest('.js-carrier-extra-content');
      if (extraContentWrapper.length === 0) {
          return;
      }

      const innerContent = extraContentWrapper.find('.vg_postnord_pickupselection_container');
      if (innerContent.length === 0) {
          return;
      }

      extraContentWrapper.css('max-height', innerContent[0].scrollHeight + 'px');
    }
});

/**
 * Store pickup point
 */
function VgPostnordStorePickupPoint(elem, servicePointId) {
    const $element = $(elem);
    const $container = $element.parents('.vg_postnord_pickupselection_container');
    const $resultsDiv = $container.find('.vg_postnord_pickup_search_results');
    const actionurl = $container.data('searchurl');

    const continueButton = $('button[name="confirmDeliveryOption"]');
    continueButton.prop('disabled', false);

    // clear selected class
    $resultsDiv.find('.vg_postnord_pickupPoint').removeClass('selected');
    $container.addClass('vg_postnord_loading');

    $.ajax({
        type: "POST",
        url: actionurl,
        data: {
            action: 'save',
            servicePointId: servicePointId,
        }
    }).done(function (resp) {
        // highlight our selected
        $element.addClass('selected');
    }).fail(function (jqXHR, textStatus) {
        console.error(jqXHR);
        $resultsDiv.find('.vg_postnord_pickupPoint').removeClass('selected');
        alert("Failed saving pickup location: " + textStatus);
    }).always(function () {
        $container.removeClass('vg_postnord_loading');
        continueButton.prop('disabled', false);
    });

}