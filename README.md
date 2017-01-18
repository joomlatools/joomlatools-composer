# Joomlatools Composer Installer

This Composer plugin will install extensions into your Joomla setup.

## Usage

### Defining a package

The easiest way to get started is by defining a custom package in your `composer.json` file.  All you need is the package file for the extension you wish to install. (ie. the installer file you downloaded from the vendor's website)

Create the `composer.json` file in the root directory of your Joomla installation and have it look something like this:

```json
	{
    	"repositories": [
        	{
            	"type": "package",
            	"package": {
                	"name": "vendor/extension",
                	"type": "joomlatools-extension",
                	"version": "1.0.0",
                	"dist": {
                    	"url": "file:////Users/johndoe/Downloads/com_extension.1.0.0.tar.gz",
                    	"type": "tar"
                	},
                	"require": {
                    	"joomlatools/composer": "*"
                	}
            	}
        	}
    	],

    	"require": {
    		"vendor/extension": "1.0.0"
    	}
	}
```

Using this JSON file, we have now defined our own custom package. Pay attention to the following settings:

* The `type` has to be set to `joomlatools-extension`
* Make sure the `url` directive points to the location of the install package.

Executing `composer install` will now fetch the `joomlatools/composer` plugin and use it to install the package into your Joomla installation.

For more information on creating these custom packages for projects which do not support Composer, see the [Composer docs](http://getcomposer.org/doc/05-repositories.md#package-2).

### Creating a custom package

To make use of all Composer's features, eg. upgrading to a newer version, you are better off creating a package using your extension's source code.

The package definition should contain the following basic information to make it installable into Joomla:

```json
{
    	"name": "vendor/my-extension",
		"type": "joomlatools-extension",
    	"require": {
        	"joomlatools/composer": "*"
    	}
}
```

If you want to make your extension available directly from Github or any other VCS, you want to make sure that the file layout in your repo resembles your install package.

You can now publish your extension on [Packagist](https://packagist.org/) or serve it yourself using your own [Satis repository](http://getcomposer.org/doc/articles/handling-private-packages-with-satis.md).

For more information on rolling your own package, please refer to the [Composer documentation](http://getcomposer.org/doc/02-libraries.md).


### Change the user

The installer injects a user called `root` into the Joomla application at runtime to make sure that the installer scripts have the necessary permissions to execute.

If for some reason, you need to change the details of this mock user, you can override them by adding a `joomla` block into the `config` section of your `composer.json`. Example:  

```json
{
    "config": {
        "joomla": {
            "username":  "johndoe",
            "name":		 "John Doe",
            "email": 	 "john@doe.com"
        }
    }
}
```

## Debugging

Having trouble? You can increase Composer's verbosity setting (`-v|vv|vvv`) to gather more information. Increasing Composer's verbosity will also enable Joomla's log messages.

## Requirements

* Composer
* Joomla version 3.6+

## Contributing

The `joomlatools/composer` plugin is an open source, community-driven project. Contributions are welcome from everyone. We have [contributing guidelines](CONTRIBUTING.md) to help you get started.

## Contributors

See the list of [contributors](https://github.com/joomlatools/joomlatools-composer/contributors).

## License 

The `joomlatools/composer` plugin is free and open-source software licensed under the [GPLv3 license](LICENSE.txt).

## Community
=======

Keep track of development and community news.

* Follow [@joomlatoolsdev on Twitter](https://twitter.com/joomlatoolsdev)
* Join [joomlatools/dev on Gitter](http://gitter.im/joomlatools/dev)
* Read the [Joomlatools Developer Blog](https://www.joomlatools.com/developer/blog/)
* Subscribe to the [Joomlatools Developer Newsletter](https://www.joomlatools.com/developer/newsletter/)

[gitflow-model]: http://nvie.com/posts/a-successful-git-branching-model/
[gplv3-license]: https://github.com/nooku/nooku-framework/blob/master/LICENSE.txt
