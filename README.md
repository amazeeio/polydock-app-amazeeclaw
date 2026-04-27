# Polydock App - AmazeeClaw AI

This package provides the AmazeeClaw Polydock AI app implementation.

It is intentionally minimal and built on top of the generic AI app so shared lifecycle logic stays upstream and only AmazeeClaw-specific behavior lives here.

## Architecture

- Base class: `FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp`
- Main app class: `Amazeeio\PolydockAppAmazeeclaw\PolydockAmazeeClawAiApp`
- Local overrides kept in this package:
  - `src/Traits/Create/PostCreateAppInstanceTrait.php`
  - `src/Traits/Create/PreCreateAppInstanceTrait.php`
  - `src/Traits/Claim/ClaimAppInstanceTrait.php`
  - `src/Traits/UsesManualAmazeeAiCredentials.php`

## AmazeeClaw-specific behavior

- Adds app instance field `openclaw_default_model`
- Adds store-app field `openclaw_default_model`; instance value overrides store-app value
- Injects `AMAZEEAI_DEFAULT_MODEL` into Lagoon project variables
- Extracts manual AI credentials from `request_data` during pre-create and stores them in the app instance secret
- Supports both flat legacy keys (`llm_key`, `llm_url`, `backend_token`, `team_id`, `vector_db_*`) and nested `ai.*` / `vector.*` secret structures
- Injects these Lagoon variables when present:
  - `AMAZEEAI_BASE_URL`
  - `AMAZEEAI_API_KEY`
  - `AMAZEEAI_BACKEND_API_TOKEN`
  - `AMAZEE_AI_TEAM_ID`
  - `AMAZEEAI_DEFAULT_MODEL`
  - `AMAZEEAI_VECTOR_DB_HOST`
  - `AMAZEEAI_VECTOR_DB_PORT`
  - `AMAZEEAI_VECTOR_DB_PASS`
  - `AMAZEEAI_VECTOR_DB_USER`
  - `AMAZEEAI_VECTOR_DB_NAME`
- Also sets `POLYDOCK_APP_NAME`, `POLYDOCK_USER_EMAIL`, `LAGOON_FEATURE_FLAG_INSIGHTS=false`, and `POLYDOCK_CLAIMED_AT`
- Claim-time command execution defaults to service `openclaw-gateway` and container `node`; claim script output must be a valid URL
- If `lagoon-deploy-project-prefix` is set, pre-create normalizes the project name and generates a unique suffix when needed

## Requirements

- PHP/Composer environment compatible with Polydock packages
- Dependency versions (see `composer.json`), especially `freedomtech-hosting/polydock-app-amazeeio-generic`

## Development

Install/update dependencies:

```bash
composer update
```

When running local workflows with Docker Compose, use detached mode:

```bash
docker-compose up -d
```
