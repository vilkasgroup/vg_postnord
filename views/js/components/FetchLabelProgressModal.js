// noinspection DuplicatedCode
export default class FetchLabelProgressModal {
  constructor(modal_id, total) {
    this.fetchLabelModal = $(`#${modal_id}`);
    this.total = total;
    this.completed = 0;

    this.updateProgress(this.completed, 1);
  }

  /**
   * Show the fetch label modal window.
   */
  show() {
    this.fetchLabelModal.modal('show');
  }

  /**
   * Hide the fetch label modal window.
   */
  hide() {
    this.fetchLabelModal.modal('hide');
  }

  /**
   * Increment the value of the progress bar by a given amount.
   *
   * @param {number} amount
   */
  incrementProgress(amount = 1) {
    this.completed = this.completed + amount;
    this.updateProgress(this.completed, this.total);
  }

  /**
   * Update the fetch label progress bar.
   *
   * @param {number} completed number of completed items
   * @param {number} total number of items in total
   */
  updateProgress(completed, total) {
    let $progressBar = this.progressBar;
    let percentage   = completed / total * 100;

    $progressBar.css('width', `${percentage}%`);
    $progressBar.find('> span').text(`${completed}/${total}`);
  }

  /**
   * Reset the modal to 'starting' state.
   */
  reset() {
    this.completed = 0;
    this.updateProgress(this.completed, 1);
    this.setLabelText(this.progressLabel.attr('default-value'));
    this.errorMessageBlock.find('.vg-postnord-fetch-label-error-message').remove();
  }

  /**
   * Set the progress bar label text to a given value.
   *
   * @param {string} text
   */
  setLabelText(text) {
    this.progressLabel.text(text);
  }

  /**
   * Add an error message to the modal.
   *
   * @param message
   */
  addErrorMessage(message) {
    let domMessage = $('<div>');

    domMessage.text(message);
    domMessage.addClass('vg-postnord-fetch-label-error-message alert alert-danger');

    this.errorMessageBlock.append(domMessage);
  }

  /**
   * Disable all the buttons inside the modal.
   */
  disableButtons() {
    this.fetchLabelModal.find('.btn').attr('disabled', true);
  }

  /**
   * Enable the close modal button.
   */
  enableCloseButton() {
    this.fetchLabelModal.find('.js-vg-postnord-close-fetcher-modal').attr('disabled', false);
  }

  /**
   * Enable the open labels button and set target URL.
   *
   * @param {string} url
   */
  enableOpenLabelsButton(url) {
    let button = $('.js-vg-postnord-open-merged-labels');
    button.click(() => {
      window.open(url, "_blank");
    })
    button.attr('disabled', false);
  }

  /**
   * Get the fetch label progress bar.
   *
   * @returns {jQuery}
   */
  get progressBar() {
    return this.fetchLabelModal.find('.progress-bar');
  }

  /**
   * Get the progress bar label.
   *
   * @returns {jQuery|HTMLElement}
   */
  get progressLabel() {
    return this.fetchLabelModal.find('.progress-details-text');
  }

  /**
   * Get the error messages block.
   *
   * @returns {jQuery|HTMLElement|*}
   */
  get errorMessageBlock() {
    return $('#vg-postnord-fetch-label-errors');
  }
}
