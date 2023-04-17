# Postnord shipping integration for Prestashop

[Postnord](https://developer.postnord.com/) integration for PrestaShop.

## Docs

Please see [documentation](https://vilkasgroup.github.io/vg_postnord/)

# Developing

```
cd modules
git clone git@github.com:vilkasgroup/vg_postnord.git
composer dump-autoload --optimize --no-dev --classmap-authoritative
```

And install the module

## Creating a new release

Remember to:
- Bump version number according to semver in the main module file
- Update CHANGELOG, change next version to the new version and add new section for next version
- Create pull request of the changes and merge to main
- Tag main for the release using format vX.X.X (same version as in the main module file)

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

## Docs

Documentation is built using Jekyll in docs folder and are update when new commits are done to `main`.

To build docks locally install ruby and bundler and run:

```
cd docs
bundle install
bundle exec jekyll serve
```

Docs use [Minimal Mistakes](https://mmistakes.github.io/minimal-mistakes/docs/quick-start-guide/) theme.

Remember to update docs side nav if you add new sections