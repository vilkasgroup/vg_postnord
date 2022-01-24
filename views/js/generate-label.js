"use strict";

function generateShippingLabel(url, id_postnord_booking) {
  $.ajax({
    url: url,
    method: "POST",
    data: {
      id_postnord_booking: id_postnord_booking
    },
    success: function(res) {
      alert("yep");
      console.log(res);
      // TODO
    },
    error: function(jqXHR, textStatus, errorThrown) {
      console.log(jqXHR, textStatus, errorThrown);
      // TODO
    }
  });
}

// the button(s) might not exist on document.ready (depends on button), so we need to use document.onclick
$(document).on("click", "[name='vg-postnord-generate-label-button']", function(e) {
  e.preventDefault();
  let url = $(this).attr("data-url");
  let id_cart_data = $(this).attr("data-id-cart-data");
  if (url && id_cart_data) {
    generateShippingLabel(url, id_cart_data)
  }
});
