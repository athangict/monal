# laminas-mvc-skeleton

## Introduction

This is a skeleton application using the Laminas MVC layer and module
systems. This application is meant to be used as a starting place for those
looking to get their feet wet with Laminas MVC.

## Project Docs

- Stripe integration (Accounts module): [docs/stripe-integration.md](docs/stripe-integration.md)

## Account hierarchy

The Chart of Account tree displays five tiers:
Class > Group > Head Type > Head > Subhead.
Head Types are assigned to Groups through the Head Type forms.
Create and Edit Head filter the Head Type choices by the selected Group.
Saving a Head requires a Head Type assigned to that Group.
Existing Heads remain under their current Group. Head Types with missing or
inconsistent Group assignments are marked for review; the tree does not move
accounts or alter balances.
Trial Balance, Balance Sheet, and Profit & Loss use the same five-tier
drill-down. Head Type subtotals sum their Heads within each existing Group,
using the report's date and location filters and signed balances.
Report exports and printing include the currently visible expanded tiers.
Copy, CSV, Excel, PDF, and Print retain the report's +/- symbols and
five-tier indentation.
Voucher detail views and their printed output display Heads as Code-Name,
matching the Sub Head Account format.
Add and Edit transaction forms use the same shared Code-Name formatter for
Head options, including additional transaction rows.
Accounts reports use Code-Name for Head and Subhead labels, including filter
choices, lazy-loaded rows, exports, and print. Stored HTML entities are decoded
once and then escaped so ampersands display correctly without rendering markup.
Run the report label regressions with:

```text
php vendor/phpunit/phpunit/phpunit --bootstrap vendor/autoload.php --no-configuration module/Accounts/test/Report
```

## Payroll slab preview

Create and Edit Pay Slab allow blank Rate, Base, and Value fields.
Blanks are stored as database NULL, not zero; explicit zero values remain zero.
This does not change payroll calculation rules.

Payroll Submit To Finance lists Bank Account records directly for the submitting
user's location. On save, the selected bank's Finance Sub Head (Bank Account
type 3) and parent Head are resolved. Missing or ambiguous mappings are
reported before any voucher is created. The net-pay
credit uses that subhead and its parent Head; no fixed account IDs are used.
The first payroll voucher for a prefix starts at serial 00001.

Add and Edit Pay Head show all Finance Sub Heads under the selected PayHead
Type's linked Head. The Head link is resolved through payroll subheads (type 5)
whose reference ID is that PayHead Type. Saving validates membership in that
Head; existing mappings are retained when compatible.

PIT Net Pay previews treat unassigned PF and GIS deductions as zero.
PIT Net Pay is Gross Pay minus active Provisional Fund (code PF) and GIS
deductions, resolved by Pay Head code rather than fixed database IDs.
Previews, payroll recalculation, Pay Head updates, and pay increments share
this calculation. Changes to PF/GIS also recalculate dependent PIT pay heads.
Non-numeric amounts are rejected rather than silently converted to zero.
Missing slabs or uncovered base amounts display a configuration error and
clear the preview amount instead of using zero or an unrelated slab.
Run the targeted regression tests with:

```text
php vendor/phpunit/phpunit/phpunit --bootstrap vendor/autoload.php --no-configuration module/Hr/test
```

## Installation using Composer

The easiest way to create a new Laminas MVC project is to use
[Composer](https://getcomposer.org/). If you don't have it already installed,
then please install as per the [documentation](https://getcomposer.org/doc/00-intro.md).

To create your new Laminas MVC project:

```bash
$ composer create-project -sdev laminas/laminas-mvc-skeleton path/to/install
```

Once installed, you can test it out immediately using PHP's built-in web server:

```bash
$ cd path/to/install
$ php -S 0.0.0.0:8080 -t public
# OR use the composer alias:
$ composer run --timeout 0 serve
```

This will start the cli-server on port 8080, and bind it to all network
interfaces. You can then visit the site at http://localhost:8080/
- which will bring up Laminas MVC Skeleton welcome page.

**Note:** The built-in CLI server is *for development only*.

## Development mode

The skeleton ships with [laminas-development-mode](https://github.com/laminas/laminas-development-mode)
by default, and provides three aliases for consuming the script it ships with:

```bash
$ composer development-enable  # enable development mode
$ composer development-disable # disable development mode
$ composer development-status  # whether or not development mode is enabled
```

You may provide development-only modules and bootstrap-level configuration in
`config/development.config.php.dist`, and development-only application
configuration in `config/autoload/development.local.php.dist`. Enabling
development mode will copy these files to versions removing the `.dist` suffix,
while disabling development mode will remove those copies.

Development mode is automatically enabled as part of the skeleton installation process. 
After making changes to one of the above-mentioned `.dist` configuration files you will
either need to disable then enable development mode for the changes to take effect,
or manually make matching updates to the `.dist`-less copies of those files.

## Running Unit Tests

To run the supplied skeleton unit tests, you need to do one of the following:

- During initial project creation, select to install the MVC testing support.
- After initial project creation, install [laminas-test](https://docs.laminas.dev/laminas-test/):

  ```bash
  $ composer require --dev laminas/laminas-test
  ```

Once testing support is present, you can run the tests using:

```bash
$ ./vendor/bin/phpunit
```

If you need to make local modifications for the PHPUnit test setup, copy
`phpunit.xml.dist` to `phpunit.xml` and edit the new file; the latter has
precedence over the former when running tests, and is ignored by version
control. (If you want to make the modifications permanent, edit the
`phpunit.xml.dist` file.)

## Using Vagrant

This skeleton includes a `Vagrantfile` based on ubuntu 22.04 (bento box)
with configured Apache2 and PHP 8.4. Start it up using:

```bash
$ vagrant up
```

Once built, you can also run composer within the box. For example, the following
will install dependencies:

```bash
$ vagrant ssh -c 'composer install'
```

While this will update them:

```bash
$ vagrant ssh -c 'composer update'
```

While running, Vagrant maps your host port 8080 to port 80 on the virtual
machine; you can visit the site at http://localhost:8080/

> ### Vagrant and VirtualBox
>
> The vagrant image is based on bento/ubuntu-22.04. If you are using VirtualBox as
> a provider, you will need:
>
> - Vagrant 2.2.6 or later
> - VirtualBox 6.0.14 or later

For vagrant documentation, please refer to [vagrantup.com](https://www.vagrantup.com/)

## Using docker-compose

This skeleton provides a `docker-compose.yml` for use with
[docker-compose](https://docs.docker.com/compose/); it
uses the provided `Dockerfile` to build a docker image 
for the `laminas` container created with `docker-compose`.

Build and start the image and container using:

```bash
$ docker-compose up -d --build
```

At this point, you can visit http://localhost:8080 to see the site running.

You can also run commands such as `composer` in the container.  The container 
environment is named "laminas" so you will pass that value to 
`docker-compose run`:

```bash
$ docker-compose run laminas composer install
```

Some composer packages optionally use additional PHP extensions.  
The Dockerfile contains several commented-out commands 
which enable some of the more popular php extensions. 
For example, to install `pdo-pgsql` support for `laminas/laminas-db`
uncomment the lines:

```sh
# RUN apt-get install --yes libpq-dev \
#     && docker-php-ext-install pdo_pgsql
```

then re-run the `docker-compose up -d --build` line as above.

> You may also want to combine the various `apt-get` and `docker-php-ext-*`
> statements later to reduce the number of layers created by your image.

## Web server setup

### Apache setup

To setup apache, setup a virtual host to point to the public/ directory of the
project and you should be ready to go! It should look something like below:

```apache
<VirtualHost *:80>
    ServerName laminasapp.localhost
    DocumentRoot /path/to/laminasapp/public
    <Directory /path/to/laminasapp/public>
        DirectoryIndex index.php
        AllowOverride All
        Order allow,deny
        Allow from all
        <IfModule mod_authz_core.c>
        Require all granted
        </IfModule>
    </Directory>
</VirtualHost>
```

### Nginx setup

To setup nginx, open your `/path/to/nginx/nginx.conf` and add an
[include directive](http://nginx.org/en/docs/ngx_core_module.html#include) below
into `http` block if it does not already exist:

```nginx
http {
    # ...
    include sites-enabled/*.conf;
}
```


Create a virtual host configuration file for your project under `/path/to/nginx/sites-enabled/laminasapp.localhost.conf`
it should look something like below:

```nginx
server {
    listen       80;
    server_name  laminasapp.localhost;
    root         /path/to/laminasapp/public;

    location / {
        index index.php;
        try_files $uri $uri/ @php;
    }

    location @php {
        # Pass the PHP requests to FastCGI server (php-fpm) on 127.0.0.1:9000
        fastcgi_pass   127.0.0.1:9000;
        fastcgi_param  SCRIPT_FILENAME /path/to/laminasapp/public/index.php;
        include fastcgi_params;
    }
}
```

Restart the nginx, now you should be ready to go!

## QA Tools

The skeleton does not come with any QA tooling by default, but does ship with
configuration for each of:

- [phpcs](https://github.com/squizlabs/php_codesniffer)
- [phpunit](https://phpunit.de)

Additionally, it comes with some basic tests for the shipped
`Application\Controller\IndexController`.

If you want to add these QA tools, execute the following:

```bash
$ composer require --dev phpunit/phpunit squizlabs/php_codesniffer laminas/laminas-test
```

We provide aliases for each of these tools in the Composer configuration:

```bash
# Run CS checks:
$ composer cs-check
# Fix CS errors:
$ composer cs-fix
# Run PHPUnit tests:
$ composer test
```
