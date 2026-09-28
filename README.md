# SHEKEL LIB
> A Laravel Package to connect Services

## Installation

[PHP](https://php.net) 8.0+ and [Composer](https://getcomposer.org) are required. Supports Laravel 9 through 13.

Add this to your composer.json

```
"repositories": {
    "dev-package": {
        "type": "vcs",
        "url": "git@github.com:Shekel-Africa/shekel-lib.git"
    }
},
```
Then require a tagged release (versions are the repository's git tags), for example:
```json
"require": {
    "shekel/shekel-lib": "^1.96.145"
}
```
Avoid `dev-master`: it follows the moving `master` branch, so installs are not reproducible.
