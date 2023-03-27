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
- Bump version number according to semver in the module file
- Update CHANGELOG, change next version to the new version and add new section for next version
- Create pull request of the changes and merge to main
- tag main for the release using format vX.X.X (same version as in the module file)

```
git tag vX.X.X
git push --tags
```

Github actions will create new release and add a zip package to it

## Running tests

Tests require apikey to be defined.

```
POSTNORD_APIKEY=asdf composer run-script test
```

You can also define `POSTNORD_HOST` if left out it will default to `atapi2.postnord.com`

get your apikey from: https://atdeveloper.postnord.com/signup
