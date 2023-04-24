const $ = window.$;

// noinspection DuplicatedCode
/**
 * SkipDisabledChoiceTable is a modified version of ChoiceTable. The original one doesn't take into account
 * the disabled state of the checkbox, this seeks to fix that.
 */
export default class SkipDisabledChoiceTable {
  /**
   * Init constructor
   */
  constructor() {
    $(document).on('change', '.js-choice-table-select-all', (e) => {
      this.handleSelectAll(e);
    });
  }

  /**
   * Check/uncheck all non-disabled boxes in table
   *
   * @param {Event} event
   */
  handleSelectAll(event) {
    const $selectAllCheckboxes = $(event.target);
    const isSelectAllChecked = $selectAllCheckboxes.is(':checked');

    $selectAllCheckboxes.closest('table').find('tbody input:checkbox').not(':disabled').prop('checked', isSelectAllChecked);
  }
}
