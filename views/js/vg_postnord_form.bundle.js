window["vg_postnord_form"] =
/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// identity function for calling harmony imports with the correct context
/******/ 	__webpack_require__.i = function(value) { return value; };
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, {
/******/ 				configurable: false,
/******/ 				enumerable: true,
/******/ 				get: getter
/******/ 			});
/******/ 		}
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = 5);
/******/ })
/************************************************************************/
/******/ ({

/***/ 0:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
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
class SkipDisabledChoiceTable {
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
/* harmony export (immutable) */ __webpack_exports__["a"] = SkipDisabledChoiceTable;



/***/ }),

/***/ 5:
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
Object.defineProperty(__webpack_exports__, "__esModule", { value: true });
/* harmony import */ var __WEBPACK_IMPORTED_MODULE_0__components_skip_disabled_choice_table__ = __webpack_require__(0);


var $ = window.$;

$(function () {
  new __WEBPACK_IMPORTED_MODULE_0__components_skip_disabled_choice_table__["a" /* default */]();
});

/***/ })

/******/ });