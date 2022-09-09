import FetchLabelProgressModal from "./FetchLabelProgressModal.js";

export default class Fetcher {
  constructor(modal_id, order_ids, url) {
    this.modal_id  = modal_id;
    this.order_ids = order_ids;
    this.url       = url;

    this.booking_ids   = [];
    this.progressModal = new FetchLabelProgressModal(modal_id, order_ids.length);

    $(document).on("click", '.js-close-fetcher-modal', () => this.progressModal.hide());
  }

  /**
   * 'Main' function.
   */
  start() {
    this.progressModal.show();
    const modalDom = $(`#${this.modal_id}`);

    modalDom.one("shown.bs.modal", async () => {
      await this.fetchLabels();
      if (this.booking_ids.length > 0) {
        await this.combineLabels();
      } else {
        // TODO: no bookings were generated - what then?
      }
    });

    modalDom.one("hidden.bs.modal", () => {
      this.progressModal.reset();
    });
  }

  /**
   * Loop through the order ids and fetch labels for each id.
   *
   * @returns {Promise<*>}
   */
  async fetchLabels() {
    let promises = [];
    for (const id of this.order_ids) {
      console.log("Fetching label for Order ID " + id);
      let data = {
        action: "fetch-label",
        id_order: id
      };
      promises.push(this.fetchLabel(data));
    }
    return (
      Promise.allSettled(promises)
    );
  }

  /**
   * Fetch labels for single order.
   *
   * @param {Object} data POST data
   *
   * @returns {*} jQuery Deferred object
   */
  fetchLabel(data) {
    return $.post({
      url: this.url,
      dataType: 'json',
      data: data,
    })
      .done((data, textStatus, jqXHR) => {
        this.handleFetchSuccess(data, textStatus, jqXHR);
      })
      .fail((jqXHR, textStatus, errorThrown) => {
        this.handleFetchError(jqXHR, textStatus, errorThrown, data);
      })
      .always(() => {
        this.progressModal.incrementProgress();
      })
      ;
  }

  /**
   * Combine and serve labels for processed orders
   *
   * @returns {Promise<void>}
   */
  async combineLabels() {
    const data = {
      action: "combine-labels",
      booking_ids: this.booking_ids
    };
    $.post({
      url: this.url,
      dataType: 'json',
      data: data,
    })
      .done((data, textStatus, jqXHR) => {
        this.generateAndServePDFBlob(data["label_data"]);
      })
      .fail((jqXHR, textStatus, errorThrown) => {
        console.log(textStatus);
        console.log(errorThrown);
        console.log(jqXHR["responseJSON"]["error"]);
      })
      .always(() => {
        // TODO: show some text?
      });
  }

  /**
   * Generate a Blob from base64 encoded PDF data, create a URL for it and open it in a new tab.
   *
   * src: https://stackoverflow.com/a/52091804
   *
   * @param raw_data Raw PDF data in base64 format
   */
  generateAndServePDFBlob(raw_data) {
    let byte_characters = atob(raw_data);
    let byteNumbers     = new Array(byte_characters.length);

    for (let i = 0; i < byte_characters.length; i++) {
      byteNumbers[i] = byte_characters.charCodeAt(i);
    }

    let byteArray = new Uint8Array(byteNumbers);
    let file      = new Blob([byteArray], { type: 'application/pdf;base64' });

    let fileUrl = URL.createObjectURL(file);
    window.open(fileUrl, "_blank");
  }

  /**
   * @param data
   * @param textStatus
   * @param jqXHR
   */
  handleFetchSuccess(data, textStatus, jqXHR) {
    console.log(data);
    if (!("id_booking" in data)) {
      console.log("Property 'id_booking' not found in response data!");
    }

    this.booking_ids.push(data["id_booking"]);
  }

  /**
   * @param jqXHR
   * @param textStatus
   * @param errorThrown
   * @param {Object} data data object that was passed to fetchLabel()
   */
  handleFetchError(jqXHR, textStatus, errorThrown, data) {
    if (!("responseJSON" in jqXHR)) {
      this.progressModal.addErrorMessage("ID " + data["id_order"] + ": " + textStatus)
      return;
    }

    console.log(jqXHR["responseJSON"]);
    if (!("error" in jqXHR["responseJSON"])) {
      console.log("Property 'error' not found in response data!");
      return;
    }

    let error = jqXHR["responseJSON"]["error"];
    this.progressModal.addErrorMessage(error);
  }
}
