<?php

namespace Amazeeio\PolydockAppAmazeeclaw\Traits;

use FreedomtechHosting\PolydockAmazeeAIBackendClient\Client;
use FreedomtechHosting\PolydockAmazeeAIBackendClient\Exception\HttpException;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;

trait UsesAmazeeAiBackend
{
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

        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $region = $appInstance->getKeyValue('amazee-ai-backend-region-id');
        if (! $region) {
            throw new PolydockAppInstanceStatusFlowException('Amazee AI backend region is required to be set in the app instance');
        }

        $amazeeAiBackendUserEmail = $appInstance->getKeyValue('amazee-ai-backend-user-email');
        if (! $amazeeAiBackendUserEmail) {
            $amazeeAiBackendUserEmail = $projectName.'@autogen.null';
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
            } else {
                $this->info('No user found in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext);
                $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
                $this->info('Creating new user in amazeeAI backend for user email: '.$amazeeAiBackendUserEmail, $logContext + ['password' => $password]);
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
        $backendCredentialName = strtolower(trim((string) preg_replace('/[^A-Za-z0-9-]+/', '-', $projectName))).'-proj-creds';

        $logContext['ai_backend_user_id'] = $backendUserId;
        $logContext['ai_backend_credential_name'] = $backendCredentialName;

        $this->info('Getting LiteLLM-only credentials from amazeeAI backend via client library', $logContext);

        $response = $this->amazeeAiBackendClient->createPrivateAIKeyToken((int) $region, $backendCredentialName, (int) $backendUserId);

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
                $this->error('Missing required credential key: '.$key, $logContext + $response);
                throw new PolydockAppInstanceStatusFlowException('Missing required credential key: '.$key);
            }
        }

        $this->info('LiteLLM credentials found via client library', $logContext + $response);

        return $response;
    }
}
