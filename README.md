# LimeSurvey REST API

A [LimeSurvey](https://github.com/LimeSurvey/LimeSurvey) plugin providing RESTful API. It's a (yet-incomplete) replacement for LimeSurvey's built-in JSON RPC interface.

## Features

The main features are:

- 🤩 **Simple and intuitive.** Start using and see.

- 🖹 **Properly documented.** With the help of a proper OpenAPI spec, an up-to-date documentation is always in front of your eyes. It should help you even if you're not familiar with LS core.

- 🔍 **Strong validation.** Plus automatic spec-based validation (which is cached for performance) thanks to [openapi-psr7-validator](https://github.com/thephpleague/openapi-psr7-validator), it relies on validations done by the core (and providing extra validations when needed). It also supports the LS permission model.

- 📦 **Docker-ready.** It auto-registers and activates itself on LimeSurvey, e.g. making it easy to be used in [limesurvey-docker](https://github.com/adamzammit/limesurvey-docker).

## Requirements

- LimeSurvey >= 5.3
- PHP >= 7.2

## How to Use?

### Installation

Download the latest ZIP file from [Releases](https://github.com/machitgarha/limesurvey-rest-api/releases). Then in LimeSurvey, from the top bar, click on "Configuration", go to "Plugins", click on "Upload and install" at the top, and select the downloaded file.

### Documentation

Alongside the ZIP file for each release, there's a standalone HTML file named `limesurvey-rest-api-docs.html`. This is the API reference for available endpoints and their information (e.g. arguments, responses, etc.).

### Updating

Repeat the steps in the "Installation" section above to update the plugin, with one more step: You have to deactivate and activate the plugin for the cache to be cleared.

### API location

The API is located at:

```text
https://your-website.com/index.php/restApi/v0/
```

## What is Implemented?

This plugin has limited functionality, i.e. it doesn't provide an endpoint for all actions. It however provides the required functionality for filling survey answers.

More precisely, you can log in as a user, get the information of surveys, their questions and question groups, and send responses for surveys. Note that, for responses, all question types are supported.

## Contributions, Please! :)

I kindly ask you that, if you have time and the ability to complete unimplemented parts, or fix or implement one of the [issues](https://github.com/machitgarha/limesurvey-rest-api/issues), then start doing so, we'll appreciate your effort for sure (and everyone will benefit from it).

## Development

### Build

In order to build plugin's ZIP file, do:

```sh
./build-aux/build-zip.sh
```

This creates a ZIP file at `build/limesurvey-rest-api.zip`. You don't need to do anything else, e.g. dependencies are automatically installed.

### Static Analysis

Phan is used as the static analyzer. In order to run Phan, you have to put a symlink to LimeSurvey at `vendor/limesurvey`. Then run:

```sh
./vendor/bin/phan --color
```

### Customize Plugin Behavior

Under the Development section of plugin settings, there are options to customize the development-related behavior of the plugin. All options are well-described.

### Documentation Generation

You can generate the documentation using any OpenAPI documentation generator. We recommend using [redoc-cli](https://github.com/Redocly/redoc).

Example of the command for documentation generation:

```sh
redoc-cli build spec/openapi.yaml -o build/docs.html
```

Which results in a standalone HTML documentation file located at `build/docs.html`

## License

[GPLv2](./LICENSE.md)
