(()=>{"use strict";var e={r:e=>{"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})}},t={};e.r(t);
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
const o=window.$;class n{constructor(){o(document).on("change",".js-choice-table-select-all",(e=>{this.handleSelectAll(e)}))}handleSelectAll(e){const t=o(e.target),n=t.is(":checked");t.closest("table").find("tbody input:checkbox").not(":disabled").prop("checked",n)}}(0,window.$)((function(){new n})),window.vg_postnord_form=t})();