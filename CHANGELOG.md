NEXT VERSION
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

20230403 v1.1.1
========
* Update necessary JavaScript dependencies
* Remove unnecessary JavaScript dependencies

20230327 v1.1.0
========
* Bump version to 1.1.0
* Support Prestashop 8
