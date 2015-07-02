CHANGELOG
=========

This changelog references the relevant changes (bug and security fixes) done
in 1.x versions.

To get the diff for a specific change, go to https://github.com/joomlatools/joomla-composer/commit/xxx where xxx is the change hash.
To view the diff between two versions, go to https://github.com/joomlatools/joomla-composer/compare/v1.0.0...v1.0.1

* 1.0.6 (2015-07-03)
 * Fixed: Use `vendor` directory instead of `tmp` to store packages. (#6)
 * Added: Support for joomla-platform. (#7)
 * Added: Uninstall support. (#9)
 * Fixed: Updated changelog.

* 1.0.5 (2015-06-11)
 * Fixed: Restrict console-plugin-api version to ^1.0 (see [this issue](https://github.com/composer/composer/issues/4085))

* 1.0.4 (2015-05-19)
 * Added - Enable Joomla logging (PR #4)

* 1.0.3 (2015-05-02)
 * Ensure compatibility with both latest Composer (Composer version 1.0-dev (1cb427ff5c0b977468643a39436f3b0a356fc8eb) 2015-04-26) and previous versions. (PR #5)

* 1.0.2 (2015-03-03)
 * Fixed - Fix call to undefined function Composer\Autoload\includeFile() error in Joomla 3.4

* 1.0.1 (2014-01-12)
 * Fixed - Joomla 3.2 compatibility ([pull request #2](https://github.com/joomlatools/joomla-composer/pull/2) by [ienev](https://github.com/ienev))
 * Fixed - Fix Joomla 3.x debug plugin crashing after execution.

* 1.0.0 (2013-11-05)
 * Added - Initial release: Composer support for Joomla 2.5 and 3.0
