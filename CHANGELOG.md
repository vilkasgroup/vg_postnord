20220518 - tsw
========
* show pickup location from cart data on order detail page (my account->orders)
* configure carrier even if it is not active

20220323 - sauli
========
* Bump version to 0.9.3
* Add base extensions to grid
* Add 'save & stay' and 'save & go to order' buttons to booking edit form
* Prevent the deletion of the last parcel of a booking
* Update parcel generation HTML

20220318 - sauli
========
* Bump version to 0.9.2
* Fix another import
* Make numeric values in parcel data null by default
* Fix weight key name in default parcel data
* Fix error during booking editing caused by empty customs declaration detailed description
* Show error when trying to fetch label if service point is not found in booking but is required by carrier

20220317 - sauli
========
* Bump version to 0.9.1
* Fix the way carrier settings are accessed in some places
* Remove some useless files
* Some refactoring and cleanup in general
* Fix a conditional causing errors before the first 'actual' saving of carrier settings
* Add custom choice table extension that skips disabled inputs
* Add base grid and form extension bundles (and required configurations)

20220311 - sauli & dat & tsw
========
* Bump version to 0.9.0
* First version released for wider testing, probably buggy
* More or less feature ready, but needs some polish, UX improvements and refactoring

20211118 - tsw
========
* initial commit, almost directly from prestashop module generator
