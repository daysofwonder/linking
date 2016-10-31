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

The Legal Part
==============

Copyright 2016 Days of Wonder &amps; Asmodee Digital

Licensed under the Apache License, Version 2.0 (the "License");
you may not use these files except in compliance with the License.
You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
