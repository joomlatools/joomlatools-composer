# Joomlatools Composer Installer

This Composer plugin will install extensions into your Joomla setup. 

Currently supporting Joomla versions 2.5 and up.

## Usage

### Defining a package

The easiest way to get started is by defining a custom package in your `composer.json`file.  All you need is the package file for the extension you wish to install. (ie. the installer file you downloaded from the vendor's website)

Create the `composer.json` file in the root directory of your Joomla installation and have it look something like this: 

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
	
Using this JSON file, we have now defined our own custom package. Pay attention to the following settings:

* The `type` has to be set to `joomlatools-installer`
* Make sure the `url` directive points to the location of the install package.

Executing `composer install` will now prepare the `joomlatools/installer` and use it to install the package into your Joomla installation.

For more information on creating custom packages, please refer to the [Composer documentation](http://getcomposer.org/doc/05-repositories.md#package-2)

### Creating a custom package

To really make use of all Composer's features, like upgrading to a newer version, you are better off creating a package using your extension's source code. 

The package definition should contain the following basic information to make it installable into Joomla: 

	{
    	"name": "vendor/my-extension",
    	"require": {
        	"joomlatools/installer": "*"
    	}
	}
	
You can now publish using a VCS repository or register your extension on Packagist. For more information on creating a Composer package, please refer to the [Composer documentation](http://getcomposer.org/doc/02-libraries.md).


### Change the user

The installer injects a user called `root` into the Joomla application at runtime to make sure that the installer scripts have the necessary permissions to execute.

If for some reason, you need to change the details of this mock user, you can do so by adding a `joomla` block into the `config` section of your `composer.json`. Example:  

    "config": {
        "joomla": {
            "username": "johndoe",
            "name":		 "John Doe",
            "email": 	 "john@doe.com"
        }
    }


## Contributing

Fork the project, create a feature branch, and send us a pull request.

## Authors

See the list of [contributors](https://github.com/joomlatools/joomla-composer/contributors).

## License

The `joomlatools/installer` plugin is licensed under the MPL license - see the LICENSE file for details.