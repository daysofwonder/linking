Introduction
============

This is an ultra-simple simulator that demonstrates the linking workflow between a PlayFab account and a PlayReal account.


Prerequisites
=============

Install php 5.4:

```
# needed for composer
brew install php54

# needed for debugging and code coverage
brew install php54-xdebug
```

Install composer:

```
brew install composer
```

or

```
curl -s https://getcomposer.org/installer | php
```

Then:

```
composer install
```

Running tests
=============

## Running the acceptance tests based on Behat
```
make test
```
