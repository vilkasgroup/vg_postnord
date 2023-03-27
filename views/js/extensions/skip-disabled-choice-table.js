/**
 * 2022 Vilkas Group Oy
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License 3.0 (OSL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 *  @author    Vilkas Group Oy <techsupport@vilkas.fi>
 *  @copyright 2022 Vilkas Group Oy
 *  @license   https://opensource.org/licenses/OSL-3.0  Open Software License 3.0 (OSL-3.0)
 *  International Registered Trademark & Property of Vilkas Group Oy
 */

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
