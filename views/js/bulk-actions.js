"use strict";

import Fetcher from "./components/Fetcher.js";

/*
 * I know this is an ugly workaround, but this is the only way I could figure out to split my code:
 * - this file has to be loaded as a module to use import statements
 *   (to load my code that has been split into different files (one class per file))
 * - modules have their own scope
 * - but the function below has to be accessible globally (for the onclick event)
 */
// noinspection DuplicatedCode
window.vgpostnordBulkFetchLabelAction = function(element, event) {
  let items = $('input[name="order_orders_bulk[]"]:checked');
  if (items.length === 0) {
    return false;
  }

  const order_ids = items.map((index, item) => item.value).get();
  const modal_id  = $(event.target).data('modal-id');
  const url       = $(event.target).data('url');

  const fetcher = new Fetcher(modal_id, order_ids, url);
  fetcher.start();
}
