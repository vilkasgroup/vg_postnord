import Grid from '@components/grid/grid';
import ReloadListExtension from '@components/grid/extension/reload-list-extension';
import SortingExtension from '@components/grid/extension/sorting-extension';
import LinkRowActionExtension from '@components/grid/extension/link-row-action-extension';

const $ = window.$;

$(() => {
  const bookingGrid = new Grid('vgpostnordbooking');

  bookingGrid.addExtension(new ReloadListExtension());
  bookingGrid.addExtension(new SortingExtension());
  bookingGrid.addExtension(new LinkRowActionExtension());
});
