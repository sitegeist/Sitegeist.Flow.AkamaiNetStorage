# Sitegeist Flow Akamai NetStorage Connector

This Flow package allows you to store assets (resources) in Akamai's NetStorage. It enables you to use Akamai NetStorage 
as a Storage or a Target in your Neos Project.

It uses the [Akamai PHP Storagekit](https://github.com/akamai/NetStorageKit-PHP)

## Authors & Sponsors

* Florian Heinze - florian.heinze@sandstorm.de

*The development and the public-releases of this package is generously sponsored by http://www.sitegeist.de.*

## Features

* storage implementation of the `WritableStorageInterface`
* target implementation of the `TargetInterface` 
* commands to be run via `./flow` e.g. to test your configuration and connectivity
* cleanup a folder to remove old assets (useful for post-deploy scripts)

With this connector you can run a Neos website without storing asset (images, PDFs etc.) on your local webserver.

## Installation

The connector is installed as a Flow package via Composer. For your existing project, simply include [TODO -> correct package name from packagist] 
into the dependencies of your Flow or Neos distribution:

`composer require [TODO]`

## Configuration

To be able to use Akamai NetStorage you have to configure your credentials in your `storageOptions` and your `targetOptions` 
in your `Settings.yaml`. For more Information on how to configure Neos check out the docs of the 
[Flow ResourceManagement](https://flowframework.readthedocs.io/en/stable/TheDefinitiveGuide/PartIII/ResourceManagement.html)

```yaml
Neos:
  Flow:
    resource:
      storages:
        akamaiPersistentResourcesStorage:
          storage: 'Sitegeist\Flow\AkamaiNetStorage\AkamaiStorage'
          storageOptions:
            host: 'YOURHOST-HERE.akamaihd.net'
            key: 'YOUR-KEY-HERE'
            keyName: 'KEY-NAME-HERE'
            cpCode: 'YOUR-CP-CODE-HERE'
            restrictedDirectory: 'functional-testcase-storage'
            workingDirectory: 'storage'
      targets:
        akamaiPersistentResourcesTarget:
          target: 'Sitegeist\Flow\AkamaiNetStorage\AkamaiTarget'
          targetOptions:
            host: 'YOURHOST-HERE.akamaihd.net'
            staticHost: 'YOUR-STATIC-HOST-HERE'
            key: 'YOUR-KEY-HERE'
            keyName: 'KEY-NAME-HERE'
            cpCode: 'YOUR-CP-CODE-HERE'
            restrictedDirectory: 'functional-testcase-storage'
            workingDirectory: 'target'
      collections:
        persistent:
          storage: 'akamaiPersistentResourcesStorage'
          target: 'akamaiPersistentResourcesTarget'
```

* **`host`**- The host of the API
* **`proxy`**- The proxy to use (if needed)
* **`staticHost`** - The host for providing static content
* **`key`** - The internally-generated Akamai Key. This is the value used when provisioning access to the API.
* **`keyName`** - The name ("Id") of an Upload Account provisioned to access the target Storage Group. 
It can be gathered from the Luna Control Center.
* **`cpCode`** - The unique CP Code that represents the root directory in the applicable NetStorage Storage Group
* **`restrictedDirectory `** - Path with additional sub-directories that the $key is restricted to
* **`workingDirectory `** - The directory, that you want to store files in, e.g. "storage" or "target" 
You need to use different working directories when configuring your storage and target.

**Do not forget to replace the upper case characters with your configuration.**

**IMPORTANT:** for all paths do Not use leading or trailing slashes!

You can test your configuration by executing the connect command:

`./flow akamai:connect`

```
Please specify the required argument "collectionName": persistent
storage connection is working
true

target connection is working
true
```

## Configuring a general connection

Beside using Akamai as a Asset Source for Neos, you can configure a general connection. This is used in the CLI tool `akamai:list` and `akamai:cleanup`.

Configuration is as follow:

```
Sitegeist:
  Flow:
    AkamaiNetStorage:
      options:
        host: 'YOURHOST-HERE.akamaihd.net'
        staticHost: 'YOUR-STATIC-HOST-HERE'
        key: 'YOUR-KEY-HERE'
        keyName: 'KEY-NAME-HERE'
        cpCode: 'YOUR-CP-CODE-HERE'
        restrictedDirectory: 'YOUR-RESTRICTED-DIRECTORY-HERE'
```

## Listing content

With the Flow CLI tool you can list content of a specific folder

`./flow akama:list --working-directory <directory>`

The `restrictedDirectory` will be taken into account, when putting together the folder.

## Cleanup

If you are using Akamai for a static asset in a deployment step, you will end up with old assets.

A helping hand with cleaning that up automatically, is the `akama:cleanup` script

`./flow akamai:cleanup --working-directory static --keep 10`

This will remove folders from the `--working-directory` and only keep the number given by the `--keep` argument.

This can be used in a post-build step.

## Running Tests

For running the tests you need an Akamai account and credentials to access NetStorage. According to our understanding 
Akamai does not seem to provide developer accounts. -> [Akamai Forum - Is there developer account for testing Open API ?](https://community.akamai.com/customers/s/question/0D50f00005RtrCZCAZ/is-there-developer-account-for-testing-open-api-?language=en_US)


Please adjust the `Settings.yaml` as follows to configure the Akamai storages for running the tests:

```yaml
Sitegeist:
  Flow:
    AkamaiNetStorage:
      functionalTests:
        storageOptions:
          host: 'YOURHOST-HERE.akamaihd.net'
          staticHost: 'YOUR-STATIC-HOST-HERE'
          key: 'YOUR-KEY-HERE'
          keyName: 'KEY-NAME-HERE'
          cpCode: 'YOUR-CP-CODE-HERE'
          restrictedDirectory: 'functional-testcase-storage'
```

**Do not forget to replace the upper case characters with your staging config before running the tests.**

`ffunctionaltest Tests/Functional`

## Further Reading

* [Flow ResourceManagement](https://flowframework.readthedocs.io/en/stable/TheDefinitiveGuide/PartIII/ResourceManagement.html)
* [Akamai PHP Storagekit](https://github.com/akamai/NetStorageKit-PHP)
* [NetStorage Usage API](https://learn.akamai.com/en-us/webhelp/netstorage/netstorage-http-api-developer-guide/GUID-22B017EE-DD73-4099-B96D-B5FD91E1ED98.html)


