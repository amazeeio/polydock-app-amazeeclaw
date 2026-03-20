<?php

namespace Amazeeio\PolydockAppAmazeeclaw\Traits;

use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;

trait UsesAmazeeAiBackend
{
    /**
     * Hook called when an app instance is being registered.
     */
    public function registerAppInstance(PolydockAppInstanceInterface $appInstance, array $data = []): void
    {
        $this->info('registerAppInstance hook: extracting AI credentials', $this->getLogContext(__FUNCTION__));
        $this->extractAndStoreAiCredentialsFromHookData($appInstance, $data);
    }

    /**
     * Hook called when an app instance is being created.
     */
    public function createAppInstance(PolydockAppInstanceInterface $appInstance, array $data = []): void
    {
        $this->info('createAppInstance hook: extracting AI credentials', $this->getLogContext(__FUNCTION__));
        $this->extractAndStoreAiCredentialsFromHookData($appInstance, $data);
    }

    /**
     * Extract AI credentials from hook data and store them in the app instance secret.
     */
    protected function extractAndStoreAiCredentialsFromHookData(PolydockAppInstanceInterface $appInstance, array $data): void
    {
        $secret = $appInstance->getKeyValue('secret') ?? [];
        if (! is_array($secret)) {
            $secret = [];
        }

        $keys = [
            'llm_key',
            'llm_url',
            'backend_token',
            'team_id',
            'vector_db_host',
            'vector_db_port',
            'vector_db_pass',
            'vector_db_user',
            'vector_db_name',
        ];

        $changed = false;
        foreach ($keys as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                $secret[$key] = $data[$key];
                $changed = true;
            }
        }

        // Support nested structures if provided
        if (isset($data['AI']) && is_array($data['AI'])) {
            $secret['AI'] = array_merge($secret['AI'] ?? [], $data['AI']);
            $changed = true;
        }
        if (isset($data['vectorDB']) && is_array($data['vectorDB'])) {
            $secret['vectorDB'] = array_merge($secret['vectorDB'] ?? [], $data['vectorDB']);
            $changed = true;
        }

        if ($changed) {
            $this->info('Stored AI credentials from hook data into app instance secret', $this->getLogContext(__FUNCTION__));
            $appInstance->storeKeyValue('secret', $secret);
            $appInstance->save();
        }
    }

    /**
     * Returns environment variables derived from stored AI credentials.
     *
     * These values may contain sensitive secrets (for example API keys, tokens, and
     * database passwords) and MUST be treated as confidential. Callers MUST NOT
     * inline these values directly into shell commands, logs, or any user-visible
     * output, and should pass them only via secure environment mechanisms.
     *
     * @return array<string, string> Environment variables containing sensitive values
     */
    public function provisionAndInjectManualAmazeeAiCredentials(PolydockAppInstanceInterface $appInstance, array $logContext = []): array
    {
        $functionName = __FUNCTION__;
        $logContext = $logContext + $this->getLogContext($functionName);

        $claimEnvVars = [];
        $secret = $appInstance->getKeyValue('secret');

        if (! is_array($secret) || empty($secret)) {
            $this->warning($functionName.': No manual AI credentials found in secret', $logContext);

            return [];
        }

        $this->info($functionName.': Using manual AI credentials from secret', $logContext);

        // Map known keys to expected environment variables
        // Support both flat structure and nested AI/vectorDB structure
        $mapping = [
            // AI / LLM
            'AI.api_key' => 'AMAZEEAI_API_KEY',
            'llm_key' => 'AMAZEEAI_API_KEY', // legacy
            'AI.llm_url' => 'AMAZEEAI_BASE_URL',
            'llm_url' => 'AMAZEEAI_BASE_URL', // legacy
            'AI.backend_token' => 'AMAZEEAI_BACKEND_API_TOKEN',
            'backend_token' => 'AMAZEEAI_BACKEND_API_TOKEN', // legacy
            'AI.team_id' => 'AMAZEE_AI_TEAM_ID',
            'team_id' => 'AMAZEE_AI_TEAM_ID', // legacy

            // Vector DB
            'vectorDB.db_host' => 'AMAZEEAI_VECTOR_DB_HOST',
            'vector_db_host' => 'AMAZEEAI_VECTOR_DB_HOST', // legacy
            'vectorDB.db_port' => 'AMAZEEAI_VECTOR_DB_PORT',
            'vector_db_port' => 'AMAZEEAI_VECTOR_DB_PORT', // legacy
            'vectorDB.db_pass' => 'AMAZEEAI_VECTOR_DB_PASS',
            'vector_db_pass' => 'AMAZEEAI_VECTOR_DB_PASS', // legacy
            'vectorDB.db_user' => 'AMAZEEAI_VECTOR_DB_USER',
            'vector_db_user' => 'AMAZEEAI_VECTOR_DB_USER', // legacy
            'vectorDB.db_name' => 'AMAZEEAI_VECTOR_DB_NAME',
            'vector_db_name' => 'AMAZEEAI_VECTOR_DB_NAME', // legacy
        ];

        foreach ($mapping as $secretPath => $envVar) {
            $val = $this->getDataFromPath($secret, $secretPath);
            if ($val !== null) {
                $claimEnvVars[$envVar] = (string) $val;
            }
        }

        // Also inject everything from secret as uppercase env vars if not already mapped
        // We only do this for top-level scalar values to avoid messy env vars from nested objects
        foreach ($secret as $key => $value) {
            if (is_scalar($value)) {
                $envVar = strtoupper((string) $key);
                if (! isset($claimEnvVars[$envVar])) {
                    $claimEnvVars[$envVar] = (string) $value;
                }
            }
        }

        $defaultModel = $this->resolveAmazeeAiDefaultModelFromInstanceOrApp($appInstance);
        if ($defaultModel !== '') {
            $claimEnvVars['AMAZEEAI_DEFAULT_MODEL'] = $defaultModel;
        }

        $this->info($functionName.': Injecting Manual AI LLM Credentials', $logContext);
        foreach ($claimEnvVars as $variableName => $variableValue) {
            $this->addOrUpdateLagoonProjectVariable($appInstance, $variableName, $variableValue, 'GLOBAL');
        }
        $this->info($functionName.': Done injecting manual AI infrastructure', $logContext);

        return $claimEnvVars;
    }

    /**
     * Simple helper to get data from a dot-notated path in an array.
     */
    protected function getDataFromPath(array $data, string $path): mixed
    {
        if (strpos($path, '.') === false) {
            return $data[$path] ?? null;
        }

        foreach (explode('.', $path) as $segment) {
            if (! is_array($data) || ! array_key_exists($segment, $data)) {
                return null;
            }

            $data = $data[$segment];
        }

        return $data;
    }

    protected function resolveAmazeeAiDefaultModelFromInstanceOrApp(PolydockAppInstanceInterface $appInstance): string
    {
        $defaultModel = '';
        if (method_exists($appInstance, 'getPolydockVariableValue')) {
            /** @phpstan-ignore-next-line */
            $defaultModel = (string) ($appInstance->getPolydockVariableValue('instance_config_openclaw_default_model') ?? '');
        }
        if ($defaultModel === '') {
            $defaultModel = (string) $appInstance->getKeyValue('instance_config_openclaw_default_model');
        }
        if ($defaultModel === '') {
            $defaultModel = (string) $appInstance->getKeyValue('app_config_openclaw_default_model');
        }
        if ($defaultModel === '') {
            /** @phpstan-ignore-next-line */
            $storeAppConfig = (array) (($appInstance->storeApp->app_config ?? null) ?: []);
            $defaultModel = (string) ($storeAppConfig['openclaw_default_model'] ?? '');
        }

        return $defaultModel;
    }

    /**
     * @param  array<string, string>  $environmentVariables
     */
    protected function buildClaimScriptWithInlineEnvironmentVariables(string $claimScript, array $environmentVariables): string
    {
        if ($claimScript === '' || count($environmentVariables) === 0) {
            return $claimScript;
        }

        $inlineVariables = [];
        foreach ($environmentVariables as $variableName => $variableValue) {
            if (! preg_match('/^[A-Z0-9_]+$/', $variableName)) {
                continue;
            }

            $inlineVariables[] = $variableName.'='.escapeshellarg($variableValue);
        }

        if (count($inlineVariables) === 0) {
            return $claimScript;
        }

        return 'env '.implode(' ', $inlineVariables).' '.$claimScript;
    }
}
