20220602 - sauli
========
* Bump version to 0.9.7
* Remove invalid logging from hookDisplayOrderDetail
* Fix code not redirecting on error when generating PDF label response
* Redirect to edit page after creating new booking
* More descriptive log messages for client when fetching labels
* Check if return label data actually exists when displaying fetch/show button text
* Check if label and return label data actually exist when trying to return existing label

20220525 - sauli
========
* Bump version to 0.9.6
* Remove invalid logging from email generation
* Pre-select a service point if the customer didn't select any (and one is required)

20220520 - sauli
========
* Bump version to 0.9.5
* Send order reference when creating booking as both reference and free text on label

20220519 - tsw & sauli
========
* Bump version to 0.9.4
* Show pickup location from cart data on order detail page (my account->orders)
* Show pickup location on order confirmation page
* Configure carrier even if it is not active
* Fill followup in shipped email for tracking
* Add setting for label paper size
* Add carrier name to bulk label action
* Add tracking codes to order carrier when fetching labels
* Set order state to 'Shipped' when fetching labels
* Display tracking codes on order page actions panel

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
