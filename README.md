# Entity XLIFF FTP [![Build Status](https://travis-ci.org/tableau-mkt/entity_xliff_ftp.svg?branch=7.x-1.x)](https://travis-ci.org/tableau-mkt/entity_xliff_ftp) [![Code Climate](https://codeclimate.com/github/tableau-mkt/entity_xliff_ftp/badges/gpa.svg)](https://codeclimate.com/github/tableau-mkt/entity_xliff_ftp) [![Dependency Status](https://gemnasium.com/tableau-mkt/entity_xliff_ftp.svg)](https://gemnasium.com/tableau-mkt/entity_xliff_ftp)

Entity XLIFF FTP is a Drupal extension that introduces a localization workflow
whereby editors or administrators can push XLIFF files to a remote server via
SFTP, then pull down processed XLIFF files when they're ready (either manually,
or automatically on cron).

This extension was designed specifically to work with [SDL WorldServer][], but
can theoretically be used with any FTP server or FTP-enabled translation
management system.

## Installation
1. Install this module and its dependency, [Composer Manager][], via drush:
  `drush dl entity_xliff_ftp composer_manager`
1. Enable Composer Manager: `drush en composer_manager`
1. Then enable this module: `drush en entity_xliff_ftp`
1. Composer Manager may automatically download and enable requisite PHP
   libraries, but if not, run `drush composer-manager install` or
   `drush composer-manager update`.

More information on [installing and using Composer Manager][] is available on
GitHub.

## Configuration
1. To access configuration pages, grant the "administer entity xliff" permission
   to all roles appropriate for your site and use-case.
1. Configure FTP credentials at `/admin/config/services/entity-xliff-ftp`.
1. On the same page, configure the "target root path" and the "source root path"
   1. The "target root path" is the path on the remote FTP server where files
      will be pushed from Drupal. Within this folder, XLIFF files will be pushed
      into sub-folders whose naming convention is like so: `en-US_to_de-DE`,
      where `de-DE` is the target language.
   1. The "source root path" is the path on the remote FTP server where this
      module will assume processed or translated XLIFFs are placed. On cron, or
      when manually triggered through the UI, this module will search this root
      within sub-folders whose naming convention is like so: `de-DE`, where
      `de-DE` is the target language.

## Usage

#### Pushing content to the remote server
Entity XLIFF, a dependency of this module, creates an "XLIFF" local task on all
entities that are known to be translatable (for example at `/node/1/xliff`). By
default, XLIFF files can be downloaded or uploaded individually for each
language.

This module adds a fieldset to this page called "Remote file sync integration,"
which shows a select list of target languages. Choose the languages you would
like this entity translated into, then press "push to remote server" to upload
XLIFF files to your configured remote server.

In the future, this module may introduce Actions and/or Rules integration along
with Views Bulk Operations integration to perform the same task on a large
number of entities simultaneously.

#### Pulling translated content from the remote server
This module exposes a simple admin UI that exposes "pending" and "processed"
XLIFFs, located at `/admin/config/regional/translate/entity-xliff-ftp`.

__Pending projects__ are sets of XLIFFs representing an entity that are ready on
the remote server, but have not yet been pulled into Drupal (meaning, the
translated XLIFFs are sitting in the "source root path" on the remote server.

__Processed projects__ are XLIFFs that have recently been pulled into Drupal.
This module knows which XLIFFs have already been imported because it moves
imported files on the remote server from the configured "source root path" to
a sub-directory called "processed."

This module will automatically check for pending projects on the remote server
and import them on cron. If you're actively working on a project or need to
import a project immediately, you can select pending projects in the
aforementioned admin UI and click the "process selected projects" button to run
a manual import.

## Please note
This module and its underlying dependencies are still under active development.
Use at your own risk with the intention of finding and filing bugs.

[SDL WorldServer]: http://www.sdl.com/cxc/language/translation-management/worldserver/
[Composer Manager]: https://www.drupal.org/project/composer_manager
[installing and using Composer Manager]: https://github.com/cpliakas/composer-manager-docs/blob/master/README.md#installation
