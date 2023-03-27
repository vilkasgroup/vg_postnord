# Postnord shipping

[Postnord](https://developer.postnord.com/) integration for PrestaShop.

## Developing

```
composer dump-autoload --optimize --no-dev --classmap-authoritative
```

## Usage

### Pickup location in email

This module adds a `{postnord_service_point}` placeholder to the order confirmation template variables,
which contains information about the selected pickup point for the order. If you want to display the information,
you will have to add the placeholder to the template manually.

There's also a variable without html for use in text-based emails: `{postnord_service_point_no_html}`

## Creating a new release
Remember to:
- Up the version number in the main module file
- Update CHANGELOG

Releases are triggered by tags matching vx.x.x being pushed, for example:
```
git tag v1.0.0
git push --tags
```

## Running tests

Tests require apikey to be defined.

```
POSTNORD_APIKEY=asdf composer run-script test
```

You can also define `POSTNORD_HOST` if left out it will default to `atapi2.postnord.com`

get your apikey from: https://atdeveloper.postnord.com/signup

