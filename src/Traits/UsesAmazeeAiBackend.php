<?php

namespace Amazeeio\PolydockAppAmazeeclaw\Traits;

use FreedomtechHosting\PolydockAmazeeAIBackendClient\Client;
use FreedomtechHosting\PolydockAmazeeAIBackendClient\Exception\HttpException;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;

trait UsesAmazeeAiBackend
{
    /**
     * @return array<string, mixed>
     */
    protected function getOrCreateTeamForAppInstance(PolydockAppInstanceInterface $appInstance, array $logContext = []): array
    {
        $adminEmail = (string) $appInstance->getKeyValue('user-email');

        if ($adminEmail === '') {
            throw new PolydockAppInstanceStatusFlowException('Polydock user-email is required to create amazee.ai team');
        }

        $teamName = strtolower($adminEmail);

        $logContext['ai_backend_team_name'] = $teamName;
        $logContext['ai_backend_team_admin_email'] = $adminEmail;

        $existingTeams = $this->amazeeAiBackendClient->listTeams();
        foreach ($existingTeams as $team) {
            if (! is_array($team)) {
                continue;
            }

            $existingTeamId = isset($team['id']) ? (string) $team['id'] : '';
            $existingTeamName = isset($team['name']) ? strtolower((string) $team['name']) : '';
            $existingTeamAdminEmail = isset($team['admin_email']) ? strtolower((string) $team['admin_email']) : '';

            if (
                $existingTeamId !== ''
                && ($existingTeamName === strtolower($teamName) || $existingTeamAdminEmail === strtolower($adminEmail))
            ) {
                $this->info('Using existing amazeeAI backend team', $logContext + ['ai_backend_team_id' => $existingTeamId]);

                return $team;
            }
        }

        try {
            $this->info('Creating amazeeAI backend team', $logContext);
            $team = $this->amazeeAiBackendClient->createTeam($teamName, $adminEmail);
            $this->info('Created amazeeAI backend team', $logContext + ['ai_backend_team_id' => $team['id'] ?? null]);

            return $team;
        } catch (HttpException $e) {
            $this->error('Error creating amazeeAI backend team', $logContext + [
                'status_code' => $e->getStatusCode(),
                'response' => $e->getResponse(),
            ]);
            throw new PolydockAppInstanceStatusFlowException('Failed to create amazeeAI backend team: '.$e->getMessage());
        }
    }

    public function setAmazeeAiBackendClientFromAppInstance(PolydockAppInstanceInterface $appInstance): void
    {
        $engine = $appInstance->getEngine();
        $this->engine = $engine;

        $amazeeAiBackendClientProvider = $engine->getPolydockServiceProviderSingletonInstance('PolydockServiceProviderAmazeeAiBackend');

        if (! method_exists($amazeeAiBackendClientProvider, 'getAmazeeAiBackendClient')) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend client provider does not have getAmazeeAiBackendClient method');
        } else {
            /** @phpstan-ignore-next-line */
            $this->amazeeAiBackendClient = $amazeeAiBackendClientProvider->getAmazeeAiBackendClient();
        }

        if (! $this->amazeeAiBackendClient) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend client not found');
        }

        if (! ($this->amazeeAiBackendClient instanceof Client)) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend client is not an instance of '.Client::class);
        }

        $region = $appInstance->getKeyValue('amazee-ai-backend-region-id');
        if (! $region) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend region is required to be set in the app instance');
        }

        if (! $this->pingAmazeeAiBackend()) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend is not healthy');
        }

        if (! $this->checkAmazeeAiBackendAuth()) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend is not authorized');
        }
    }

    public function checkAmazeeAiBackendAuth(): bool
    {
        $logContext = $this->getLogContext(__FUNCTION__);

        $this->info('Checking amazeeAI backend auth', $logContext);

        $response = $this->amazeeAiBackendClient->getMe();

        if (! $response['is_admin']) {
            $this->error('Amazee AI backend is not authorized as an admin', $logContext + $response);
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend is not authorized as an admin');
        }

        if (! $response['is_active']) {
            $this->error('Amazee AI backend is not an active admin', $logContext + $response);
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend is not an active admin');
        }

        $this->info('Amazee AI backend is authorized and active', $logContext + $response);

        return true;
    }

    public function pingAmazeeAiBackend(): bool
    {
        $logContext = $this->getLogContext(__FUNCTION__);

        if (! $this->amazeeAiBackendClient) {
            throw new PolydockAppInstanceStatusFlowException('amazeeAI backend client not found for ping');
        }

        try {
            $response = $this->amazeeAiBackendClient->health();

            if (is_array($response) && isset($response['status'])) {
                if ($response['status'] === 'healthy') {
                    $this->info('amazeeAI backend is healthy', $logContext + $response);

                    return true;
                } else {
                    $this->error('amazeeAI backend is not healthy: ', $logContext + $response);

                    return false;
                }
            } else {
                $this->error('Error pinging amazeeAI backend: ', $logContext + $response);

                return false;
            }
        } catch (\Exception $e) {
            $this->error('Error pinging amazeeAI backend: ', $logContext + ['error' => $e->getMessage()]);
            throw new PolydockAppInstanceStatusFlowException('Error pinging Lagoon API: '.$e->getMessage());
        }
    }

    public function getLiteLlmCredentialsFromBackend(PolydockAppInstanceInterface $appInstance): array
    {
        $logContext = $this->getLogContext(__FUNCTION__);

        if (! $this->checkAmazeeAiBackendAuth()) {
            $this->error('Amazee AI backend is not authorized', $logContext);
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend is not authorized');
        }

        if (! $this->pingAmazeeAiBackend()) {
            $this->error('Amazee AI backend is not healthy', $logContext);
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend is not healthy');
        }

        $region = $appInstance->getKeyValue('amazee-ai-backend-region-id');
        if (! $region) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend region is required to be set in the app instance');
        }

        $amazeeAiBackendUserEmail = (string) $appInstance->getKeyValue('user-email');
        if ($amazeeAiBackendUserEmail === '') {
            throw new PolydockAppInstanceStatusFlowException('Polydock user-email is required to create amazee.ai backend user');
        }

        $logContext['ai_backend_region'] = $region;
        $logContext['ai_backend_user_email'] = $amazeeAiBackendUserEmail;

        $this->info('Searching for user in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);
        $backendUserList = $this->amazeeAiBackendClient->searchUsers($amazeeAiBackendUserEmail);
        $this->info('Found '.count($backendUserList).' users in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);

        $backendUser = null;
        try {
            if (count($backendUserList) > 1) {
                $this->info('Multiple users found in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);
                $backendUser = $backendUserList[0];
                $this->info('Using first user found in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);
            } elseif (count($backendUserList) === 1) {
                $backendUser = $backendUserList[0];
                $this->info('Using existing user found in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);
            } else {
                $this->info('No user found in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);
                $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
                $this->info('Creating new user in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);
                $backendUser = $this->amazeeAiBackendClient->createUser($amazeeAiBackendUserEmail, $password);
                $this->info('Created new user in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext + $backendUser);
            }
        } catch (HttpException $e) {
            $this->error('Error creating user in amazeeAI backend', $logContext + [
                'status_code' => $e->getStatusCode(),
                'response' => $e->getResponse(),
            ]);
        }

        if (! $backendUser) {
            $this->error('Failed to create user in amazeeAI backend', $logContext);
            throw new PolydockAppInstanceStatusFlowException('Failed to create user in amazeeAI backend');
        }

        $backendUserId = $backendUser['id'];

        $logContext['ai_backend_user_id'] = $backendUserId;

        $team = $this->getOrCreateTeamForAppInstance($appInstance, $logContext);
        if (! is_array($team) || ! isset($team['id'])) {
            throw new PolydockAppInstanceStatusFlowException('Failed to find or create amazeeAI backend team');
        }

        $teamId = (int) $team['id'];
        $logContext['ai_backend_team_id'] = $teamId;
        $backendCredentialName = $this->buildSharedAmazeeClawCredentialName($teamId, (int) $region);
        $logContext['ai_backend_credential_name'] = $backendCredentialName;

        $appInstance->storeKeyValue('amazee-ai-team-id', (string) $teamId);
        if (isset($team['name'])) {
            $appInstance->storeKeyValue('amazee-ai-team-name', (string) $team['name']);
        }

        $existingUserTeamId = isset($backendUser['team_id']) ? (int) $backendUser['team_id'] : 0;
        if ($existingUserTeamId > 0 && $existingUserTeamId !== $teamId) {
            $this->error('User already belongs to a different amazeeAI backend team', $logContext + [
                'existing_ai_backend_team_id' => $existingUserTeamId,
            ]);
            throw new PolydockAppInstanceStatusFlowException('User already belongs to a different amazeeAI backend team');
        }

        if ($existingUserTeamId !== $teamId) {
            try {
                $this->info('Adding user to amazeeAI backend team', $logContext);
                $this->amazeeAiBackendClient->addUserToTeam((int) $backendUserId, $teamId);
                $this->info('Added user to amazeeAI backend team', $logContext);
            } catch (HttpException $e) {
                $this->error('Error adding user to amazeeAI backend team', $logContext + [
                    'status_code' => $e->getStatusCode(),
                    'response' => $e->getResponse(),
                ]);
                throw new PolydockAppInstanceStatusFlowException('Failed to add user to amazeeAI backend team: '.$e->getMessage());
            }
        }

        $this->info('Getting LiteLLM-only credentials from amazeeAI backend via client library', $logContext);

        $response = $this->findReusableAmazeeClawCredential($backendCredentialName, (int) $region, $logContext);
        if ($response !== null) {
            $this->info('Reusing existing amazeeAI backend key token', $logContext + [
                'ai_backend_credential_name' => $backendCredentialName,
            ]);
        } else {
            $this->info('Creating amazeeAI backend key token', $logContext + [
                'ai_backend_credential_name' => $backendCredentialName,
            ]);
            $response = $this->amazeeAiBackendClient->createPrivateAIKeyToken((int) $region, $backendCredentialName, 0, $teamId);
        }

        if (! $response || ! is_array($response)) {
            $this->error('No AI credentials found', $logContext);
            throw new PolydockAppInstanceStatusFlowException('No AI credentials found');
        }

        $requiredKeys = [
            'litellm_token',
            'litellm_api_url',
        ];

        foreach ($requiredKeys as $key) {
            if (! isset($response[$key])) {
                $this->error('Missing required credential key: '.$key, $logContext);
                throw new PolydockAppInstanceStatusFlowException('Missing required credential key: '.$key);
            }
        }

        $response['amazeeai_team_id'] = $teamId;

        $backendApiTokenName = $backendCredentialName.'-backend-api';
        $this->info('Creating Amazee AI backend API token', $logContext + ['ai_backend_token_name' => $backendApiTokenName]);
        $backendApiTokenResponse = $this->amazeeAiBackendClient->createToken($backendApiTokenName, (int) $backendUserId);
        if (! is_array($backendApiTokenResponse) || ! isset($backendApiTokenResponse['token'])) {
            $this->error('Missing required credential key: token', $logContext + ['ai_backend_token_name' => $backendApiTokenName]);
            throw new PolydockAppInstanceStatusFlowException('Missing required credential key: token');
        }

        $response['amazeeai_backend_api_token'] = $backendApiTokenResponse['token'];
        $this->info('LiteLLM and backend API credentials created via client library', $logContext + ['ai_backend_token_name' => $backendApiTokenName]);

        return $response;
    }

    /**
     * @return array<string, string> Env vars safe to inline into claim script command
     */
    protected function provisionAndInjectAmazeeAiCredentialsForClaim(PolydockAppInstanceInterface $appInstance, array $logContext = []): array
    {
        $functionName = __FUNCTION__;
        $logContext = $logContext + $this->getLogContext($functionName);

        $this->setAmazeeAiBackendClientFromAppInstance($appInstance);
        $privateAiCredentials = $this->getLiteLlmCredentialsFromBackend($appInstance);

        $claimEnvVars = [
            'AMAZEEAI_BASE_URL' => (string) $privateAiCredentials['litellm_api_url'],
            'AMAZEEAI_API_KEY' => (string) $privateAiCredentials['litellm_token'],
            'AMAZEEAI_BACKEND_API_TOKEN' => (string) $privateAiCredentials['amazeeai_backend_api_token'],
        ];
        $defaultModel = $this->resolveAmazeeAiDefaultModelFromInstanceOrApp($appInstance);
        if ($defaultModel !== '') {
            $claimEnvVars['AMAZEEAI_DEFAULT_MODEL'] = $defaultModel;
        }

        $this->info($functionName.': Injecting AI LLM Credentials', $logContext);
        foreach ($claimEnvVars as $variableName => $variableValue) {
            $this->addOrUpdateLagoonProjectVariable($appInstance, $variableName, $variableValue, 'GLOBAL');
        }
        $this->info($functionName.': Done injecting AI infrastructure', $logContext);

        return $claimEnvVars;
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

    private function buildSharedAmazeeClawCredentialName(int $teamId, int $regionId): string
    {
        return 'amazeeclaw-team-'.$teamId.'-region-'.$regionId;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findReusableAmazeeClawCredential(string $credentialName, int $regionId, array $logContext = []): ?array
    {
        try {
            $allKeys = $this->amazeeAiBackendClient->listPrivateAIKeys();
        } catch (\Throwable $e) {
            $this->warning('Unable to list existing amazeeAI backend keys, will create a new one', $logContext + [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        foreach ($allKeys as $key) {
            if (! is_array($key)) {
                continue;
            }

            $name = isset($key['name']) ? (string) $key['name'] : '';
            if ($name !== $credentialName) {
                continue;
            }

            if ($this->doesKeyMatchRegion($key, $regionId) !== true) {
                continue;
            }

            $token = isset($key['litellm_token']) ? (string) $key['litellm_token'] : '';
            $apiUrl = isset($key['litellm_api_url']) ? (string) $key['litellm_api_url'] : '';
            if ($token === '' || $apiUrl === '') {
                $this->warning('Found matching amazeeAI key without complete token payload; creating a new one', $logContext + [
                    'ai_backend_credential_name' => $credentialName,
                ]);

                return null;
            }

            return [
                'litellm_token' => $token,
                'litellm_api_url' => $apiUrl,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $key
     */
    private function doesKeyMatchRegion(array $key, int $regionId): bool
    {
        if (isset($key['region_id']) && (int) $key['region_id'] === $regionId) {
            return true;
        }

        if (isset($key['region'])) {
            $regionValue = (string) $key['region'];
            if (is_numeric($regionValue) && (int) $regionValue === $regionId) {
                return true;
            }

            // Some API responses expose region name/label, not ID.
            // In that case, credential-name matching is already strict.
            return true;
        }

        // If region metadata is absent, trust the deterministic credential name.
        return true;
    }
}
