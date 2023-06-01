# centreon-custom-views-management

## Introduction

This community module allows you to administrate custom views for you users. You must use an administrator account in Centreon to use it.

## What it does

- Take control of a custom view that is shared to a user. The user doesn't need to be owner of the said custom view. The custom view doesn't need to be consumed by the user.
- Give back control of a custom view to the user that was the owner before you seized it.
- Share one of your custom views to another user (this include your custom views and the one you've take control of).
- Sharing a custom view gives you the possibility to set it as locked/unlocked and force its consumption to the user
- Display proper errors if something is wrong in your database

## What it doesn't

- Sharing options are limited to users, you can't share to user groups. (may come in a later release)
- Will not bring an additional olympic gold medal to your favorite country

## Installation

- Download the latest release of this Centreon module https://github.com/tanguyvda/centreon-custom-views-management/releases/
- unzip the archive and put the centreon-custom-views-management folder inside the /usr/share/centreon/www/modules/ directory of your central server
- install the module through the web interface in the administration->extension->modules menu

You now have a new custom-views-management tab in the configuration menu

## Screenshots

### Import custom views from a user

![user custom view listing for import](screenshots/import_views.png)

### List of imported custom views

![custom views imported from users](screenshots/imported_views.png)

### Custom views sharing options

![share custom views to a user](screenshots/share_views.png)

### Error management

![display error](screenshots/error_management.png)
