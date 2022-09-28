# LimeSurvey REST API

A LimeSurvey plugin providing RESTful API. It's a (yet-incomplete) replacement for LimeSurvey's built-in JSON RPC interface.

## Features

The main features are:

- 🤩 **Simple and intuitive.** Start using and see.

- 🖹 **Properly documented.** With the help of a proper OpenAPI spec, an up-to-date documentation is always in front of your eyes. It should help you even if you're not familiar with LS core.

- 🔍 **Strong validation.** Plus automatic spec-based validation (which is cached for performance) thanks to [openapi-psr7-validator](https://github.com/thephpleague/openapi-psr7-validator), it relies on validations done by the core (and providing extra validations when needed). It also supports the LS permission model.

- 📦 **Docker-ready.** It auto-registers and activates itself on LimeSurvey, e.g. making it easy to be used in [limesurvey-docker](https://github.com/adamzammit/limesurvey-docker).

## Requirements

- LimeSurvey >= 5.3
- PHP >= 7.2

## How to Install?

Download the latest ZIP file from [Releases](https://github.com/machitgarha/limesurvey-rest-api/releases). Then in LimeSurvey, from the top bar, click on "Configuration", go to "Plugins", click on "Upload and install" at the top, and select the downloaded file.

### Updating

Repeat the steps above to update the plugin, with one more step: You have to deactivate and activate the plugin for the cache to be cleared.

## What is Implemented?

This plugin has limited functionality, i.e. it doesn't provide an endpoint for all actions. It however provides the required functionality for filling survey answers.

More precisely, you can log in as a user, get the information of surveys, their questions and question groups, and send responses for surveys. Note that, for responses, all question types are supported.

## License

[GPLv2](./LICENSE.md)
