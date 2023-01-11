import FetchLabelProgressModal from "./FetchLabelProgressModal.js";

// noinspection DuplicatedCode
export default class Fetcher {
  constructor(modal_id, order_ids, url) {
    this.modal_id  = modal_id;
    this.order_ids = order_ids;
    this.url       = url;

    this.booking_ids   = [];
    this.translations  = [];
    this.progressModal = new FetchLabelProgressModal(modal_id, order_ids.length);

    $(document).on("click", '.js-vg-postnord-close-fetcher-modal', () => this.progressModal.hide());
  }

  /**
   * 'Main' function.
   */
  start() {
    this.parseTranslations();
    this.progressModal.disableButtons();
    this.progressModal.show();
    const modalDom = $(`#${this.modal_id}`);

    modalDom.one("shown.bs.modal", async () => {
      await this.fetchLabels();
      if (this.booking_ids.length > 0) {
        this.progressModal.setLabelText(this.translations["merging-labels"]);
        await this.combineLabels();
      } else {
        this.progressModal.addErrorMessage(this.translations["no-bookings-generated"]);
        this.progressModal.setLabelText(this.translations["done"]);
      }
      this.progressModal.enableCloseButton();
    });

    modalDom.one("hidden.bs.modal", () => {
      window.location.reload();
    });
  }

  /**
   * Parse JSON translations from inside the modal template into an array.
   */
  parseTranslations() {
    let json = $("#vg-postnord-fetch-label-translations").text()
    this.translations = JSON.parse(json);
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
      promises.push(await this.fetchLabel(data));
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
  async fetchLabel(data) {
    return $.post({
      url: this.url,
      dataType: 'json',
      data: data,
    })
      .done((data, textStatus, jqXHR) => {
        this.handleFetchSuccess(data, textStatus, jqXHR);
      })
      .catch((e) => {
        this.handleFetchError(e, data);
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
        this.handleCombineSuccess(data, textStatus, jqXHR);
      })
      .fail((jqXHR, textStatus, errorThrown) => {
        this.handleCombineError(jqXHR, textStatus, errorThrown, data);
      })
    ;
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
    this.progressModal.enableOpenLabelsButton(fileUrl);
    window.open(fileUrl, "_blank");
  }

  /**
   * @param data
   * @param textStatus
   * @param jqXHR
   */
  handleFetchSuccess(data, textStatus, jqXHR) {
    console.log(data["success"]);
    if (!("id_booking" in data)) {
      console.log("Property 'id_booking' not found in response data!");
      return;
    }

    this.booking_ids.push(data["id_booking"]);
  }

  /**
   * @param response
   * @param {Object} data data object that was passed to fetchLabel()
   */
  handleFetchError(response, data) {
    if (!("responseJSON" in response)) {
      this.progressModal.addErrorMessage("ID " + data["id_order"] + ": " + response["statusText"])
      return;
    }

    console.log(response["responseJSON"]);
    if (!("error" in response["responseJSON"])) {
      console.log("Property 'error' not found in response data!");
      return;
    }

    let error = response["responseJSON"]["error"];
    this.progressModal.addErrorMessage(error);
  }

  /**
   * @param data
   * @param textStatus
   * @param jqXHR
   */
  handleCombineSuccess(data, textStatus, jqXHR) {
    this.generateAndServePDFBlob(data["label_data"]);
    this.progressModal.setLabelText(data["success"]);
  }

  /**
   * @param jqXHR
   * @param textStatus
   * @param errorThrown
   * @param {object} data data object that was passed to combineLabels()
   */
  handleCombineError(jqXHR, textStatus, errorThrown, data) {
    if (!("responseJSON" in jqXHR)) {
      this.progressModal.addErrorMessage(this.translations["error-merging"] + errorThrown);
      return;
    }

    console.log(jqXHR["responseJSON"]);
    if (!("error" in jqXHR["responseJSON"])) {
      console.log("Property 'error' not found in response data!");
      return;
    }

    let error = jqXHR["responseJSON"]["error"];
    this.progressModal.addErrorMessage(error);
    this.progressModal.setLabelText(this.translations["error"]);
  }
}
