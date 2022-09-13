# LimeSurvey REST API

A LimeSurvey plugin providing RESTful API. It's a (yet-incomplete) replacement for LimeSurvey's built-in JSON RPC interface.

## Features

The main features are:

- 🤩 **Simple and intuitive.** Start using and see.

- 🖹 **Properly documented.** With the help of a proper OpenAPI spec, an up-to-date documentation is always in front of your eyes. It should help you even if you're not familiar with LS core.

- 🔍 **Strong validation.** Plus automatic spec-based validation thanks to [openapi-psr7-validator](https://github.com/thephpleague/openapi-psr7-validator), it relies on validations done by the core (and providing extra validations when needed). It also supports the LS permission model.

- 📦 **Docker-ready.** It auto-registers and activates itself on LimeSurvey, e.g. making it easy to be used in [limesurvey-docker](https://github.com/adamzammit/limesurvey-docker).  

And more: Handling errors is easy, and the spec is cached for performance.

## License

[GPLv2](./LICENSE.md)
