# Quickbooks Helper

PHP client wrapping the [Quickbooks PHP SDK](https://github.com/intuit/QuickBooks-V3-PHP-SDK).

## Installation

1. Install the package:

```bash
$ composer require codemonkey76/quickbooks-helper
```

2. Run our migration to install the `quickbooks_tokens` table:

```bash
php artisan migrate --package=codemonkey76/quickbooks-helper
```

The package uses the [auto registration feature](https://laravel.com/docs/packages#package-discovery) of Laravel.

## Configuration

1. You will need a ```quickBooksToken``` relationship on your ```User``` model.  There is a trait named ```Codemonkey76\QuickBooks\HasQuickBooksToken```, which you can include on your ```User``` model, which will setup the relationship. To do this implement the following:

Add ```use Codemonkey76\QuickBooks\HasQuickBooksToken;``` to your service container at the top of User.php
and also add the trait within the class. For example:

```php
class User extends Authenticatable
{
    use Notifiable, HasQuickBooksToken;
```

**NOTE: If your ```User``` model is not ```App/User```, then you will need to configure the path in the ```configs/quickbooks.php``` as documented below.**

2. Add the appropriate values to your ```.env```

    #### Minimal Keys
    ```bash
    QUICKBOOKS_CLIENT_ID=<client id given by QuickBooks>
    QUICKBOOKS_CLIENT_SECRET=<client secret>
    ```

    #### Optional Keys
    ```bash
    QUICKBOOKS_API_URL=<Development|Production> # Defaults to App's env value
    QUICKBOOKS_DEBUG=<true|false>               # Defaults to App's debug value
    ```

    3. _[Optional]_ Publish configs & views

    #### Config
    A configuration file named ```quickbooks.php``` can be published to ```config/``` by running...

    ```bash
    php artisan vendor:publish --tag=quickbooks-config
    ```

    #### Views
    View files can be published by running...

    ```bash
    php artisan vendor:publish --tag=quickbooks-views
    ```

## Usage

Here is an example of getting the company information from QuickBooks:

### NOTE: Before doing these commands, go to your connect route (default: /quickbooks/connect) to get a QuickBooks token for your user

```php
php artisan tinker
Psy Shell v0.11.2 (PHP 8.1.2 — cli) by Justin Hileman
>>> Auth::logInUsingId(1)
=> App\User {#1668
     id: 1,
     // Other keys removed for example
   }
>>> $quickbooks = app('Quickbooks')
=> Codemonkey76\QuickBooks\QuickbooksClient {#1613}
>>> $quickbooks->getDataService()->getCompanyInfo();
=> QuickBooksOnline\API\Data\IPPCompanyInfo {#1673
     +CompanyName: "Sandbox Company_US_1",
     +LegalName: "Sandbox Company_US_1",
     // Other properties removed for example
   }
>>>
```

You can call any of the resources as documented [in the SDK](https://intuit.github.io/QuickBooks-V3-PHP-SDK/quickstart.html).

## Middleware

If you have routes that will be dependent on the user's account having a usable QuickBooks OAuth token, there is an included middleware ```Codemonkey76\Quickbooks\Http\Middleware\QuickbooksConnected``` that gets registered as ```quickbooks``` that will ensure the account is linked and redirect them to the `connect` route if needed.

Here is an example route definition:

```php
Route::view('some/route/needing/quickbooks/token/before/using', 'some.view')
     ->middleware('quickbooks');
```