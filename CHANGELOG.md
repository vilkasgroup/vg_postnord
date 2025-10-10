NEXT VERSION
========

20251010 v1.1.4
========
* Add support for PrestaShop 9
* Drop support for PrestaShop 1.7
* Raise minimum PHP version to 8.1
* Add a new carrier setting: Enable pickup point selection
  * Pickup point selector no longer depends on the PostNord API reporting the "A7" (Optional service point)
    additional service as mandatory
* Fix pickup point selector compatibility with the Hummingbird theme
* Fix a bug where removing booking content lines depended on the number of parcels in the booking
* Refactor and optimize carrier settings JavaScript
  * Do most of the additional service filtering in PHP instead
* Fix "ALL" countries additional services not being selectable
* Update field names that have changed in the PostNord API

20230829 v1.1.3
========
* Fix undefined array key warning on module configuration page after module installation

20230627 v1.1.2
========
* Convert additional_service_codes inside carrier configurations from JSON string to array
* Take additional service codes into account when displaying pickup selector and doing order validation operations
* Remove old licenses from files
* Use the "displayHeader" hook instead of the deprecated "header" hook
* Move Party ID from address settings to basic settings
* Check Party ID validity when saving module settings form
* Filter out some service codes from being selectable:
  - InNight (48)
  - InNight Reverse (49)
  - Retail Delivery (59)
  - PostNord Part Loads (85)
  - Domestic Road (99)
* Fix a bug where an error would be displayed when saving carrier settings if the save handler couldn't find any
  valid service code / country combinations
* Filter out outdoor lockers if 'M7' additional service (Not to outdoor parcel locker) is selected
* Check that the order is a PostNord order before trying to fetch label using ajax

20230403 v1.1.1
========
* Update necessary JavaScript dependencies
* Remove unnecessary JavaScript dependencies

20230327 v1.1.0
========
* Bump version to 1.1.0
* Support Prestashop 8
