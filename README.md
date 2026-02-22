# Polydock App - AmazeeClaw AI

This package provides the AmazeeClaw Polydock AI app implementation.

It is intentionally minimal and built on top of the generic AI app so shared lifecycle logic stays upstream and only AmazeeClaw-specific behavior lives here.

## Architecture

- Base class: `FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp`
- Main app class: `Amazeeio\PolydockAppAmazeeclaw\PolydockAmazeeClawAiApp`
- Local overrides kept in this package:
  - `src/Traits/Create/PostCreateAppInstanceTrait.php`
  - `src/Traits/UsesAmazeeAiBackend.php`

## AmazeeClaw-specific behavior

- Adds app instance field `openclaw_default_model`
- Injects `MAZ_OPENCLAW_DEFAULT_MODEL` into Lagoon project variables
- Creates/reuses an amazee.ai team (`gpt-<lagoon-project-name>`) using the app instance user email
- Uses team-owned LiteLLM token creation via
  `Client::createPrivateAIKeyToken()` from `freedomtech-hosting/polydock-amazeeai-backend-client-php`
- Injects:
  - `AMAZEEAI_BASE_URL`
  - `AMAZEEAI_API_KEY`
  - `AMAZEE_AI_TEAM_ID`

## Requirements

- PHP/Composer environment compatible with Polydock packages
- Dependency versions (see `composer.json`), especially:
  - `freedomtech-hosting/polydock-app-amazeeio-generic`
  - `freedomtech-hosting/polydock-amazeeai-backend-client-php ^0.1`

## Development

Install/update dependencies:

```bash
composer update
```

When running local workflows with Docker Compose, use detached mode:

```bash
docker-compose up -d
```
