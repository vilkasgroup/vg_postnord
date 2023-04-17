---
permalink: /docs/
title: "Prestashop Postnord Documentation"
---

# NOTE: This documentation is WIP

## Installation

### Download the latest relase

You can download the latest release from [Github Releases](https://github.com/vilkasgroup/vg_postnord/releases/latest) page

Note download the generated zip file, not the automatically generated "Source code" packages.

### Upload the module to your shop

In your shop admin `Module Manager` use the "Upload module" button to upload the module into your shop.

{% include figure image_path="/assets/images/upload_module.png" alt="Upload module screenshot" caption="" %}

The same upload button can be used to update the module.

### Emails

### Pickup location in email

This module adds a `{postnord_service_point}` placeholder to the order confirmation template variables,
which contains information about the selected pickup point for the order. If you want to display the information,
you will have to add the placeholder to the template manually.

There's also a variable without html for use in text-based emails: `{postnord_service_point_no_html}`

## Configuration

### Requirements

You need `API Key` from Postnord to continue.

### Basic settings

### Carrier settings

#### Create carrier configurations

Using Prestashop default configuration create the carriers you want for the site. For example: Create two carriers:

* Postnord home delivery
* Postnord pickup locations

And configure areas, prices and other settings as usuall with Prestashop.

In the next step we will configure these carriers to use postnord.

#### Configure carriers to use postnord

TODO

### Sender address

TODO

### Return address

TODO

-----------------

## Pickuplocations in storefront

TODO

## Generating labels

TODO