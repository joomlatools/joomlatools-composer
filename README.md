# Joomlatools Composer Installer

This Composer plugin will install extensions into your Joomla setup. 

## Usage

### Defining a package

The easiest way to get started is by defining a custom package in your `composer.json`file.  All you need is the package file for the extension you wish to install. (ie. the installer file you downloaded from the vendor's website)

Create the `composer.json` file in the root directory of your Joomla installation and have it look something like this: 

```json
	{
    	"repositories": [
        	{
            	"type": "package",
            	"package": {
                	"name": "vendor/extension",
                	"type": "joomlatools-installer",
                	"version": "1.0.0",
                	"dist": {
                    	"url": "file:////Users/johndoe/Downloads/com_extension.1.0.0.tar.gz",
                    	"type": "tar"
                	},
                	"require": {
                    	"joomlatools/installer": "*"
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

* The `type` has to be set to `joomlatools-installer`
* Make sure the `url` directive points to the location of the install package.

Executing `composer install` will now fetch the `joomlatools/installer` plugin and use it to install the package into your Joomla installation.

For more information on creating these custom packages for projects which do not support Composer, see the [Composer docs](http://getcomposer.org/doc/05-repositories.md#package-2).

### Creating a custom package

To make use of all Composer's features, eg. upgrading to a newer version, you are better off creating a package using your extension's source code. 

The package definition should contain the following basic information to make it installable into Joomla: 

```json
{
    	"name": "vendor/my-extension",
    	"require": {
        	"joomlatools/installer": "*"
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
            "username": "johndoe",
            "name":		 "John Doe",
            "email": 	 "john@doe.com"
        }
    }
}
```

## Requirements

* Composer
* Joomla version 2.5 and up.

## Contributing

Fork the project, create a feature branch, and send us a pull request.

## Authors

See the list of [contributors](https://github.com/joomlatools/joomla-composer/contributors).

## License

The `joomlatools/installer` plugin is licensed under the GPL v3 license - see the LICENSE file for details.