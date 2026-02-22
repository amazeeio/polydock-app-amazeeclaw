<?php

declare(strict_types=1);

namespace Amazeeio\PolydockAppAmazeeclaw;

use Amazeeio\PolydockAppAmazeeclaw\Traits\Claim\ClaimAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Create\CreateAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Create\PostCreateAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Create\PreCreateAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Deploy\DeployAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Deploy\PollDeployProgressAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Deploy\PostDeployAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Deploy\PreDeployAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Health\PollHealthProgressAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Remove\PostRemoveAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Remove\PreRemoveAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Remove\RemoveAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Upgrade\PollUpgradeProgressAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Upgrade\PostUpgradeAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Upgrade\PreUpgradeAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Upgrade\UpgradeAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\UsesAmazeeAiBackend;
use Filament\Forms;
use Filament\Infolists;
use FreedomtechHosting\FtLagoonPhp\Client as LagoonClient;
use FreedomtechHosting\FtLagoonPhp\LagoonClientInitializeRequiredToInteractException;
use FreedomtechHosting\PolydockAmazeeAIBackendClient\Client as AmazeeAiBackendClient;
use FreedomtechHosting\PolydockApp\Attributes\PolydockAppInstanceFields;
use FreedomtechHosting\PolydockApp\Attributes\PolydockAppStoreFields;
use FreedomtechHosting\PolydockApp\Attributes\PolydockAppTitle;
use FreedomtechHosting\PolydockApp\Contracts\HasAppInstanceFormFields;
use FreedomtechHosting\PolydockApp\Contracts\HasStoreAppFormFields;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppBase;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;
use FreedomtechHosting\PolydockApp\PolydockAppVariableDefinitionBase;
use FreedomtechHosting\PolydockApp\PolydockAppVariableDefinitionInterface;
use FreedomtechHosting\PolydockApp\PolydockEngineInterface;
use FreedomtechHosting\PolydockApp\PolydockServiceProviderInterface;

#[PolydockAppTitle('AmazeeClaw AI App')]
#[PolydockAppStoreFields]
#[PolydockAppInstanceFields]
class PolydockAmazeeClawAiApp extends PolydockAppBase implements HasAppInstanceFormFields, HasStoreAppFormFields
{
    use ClaimAppInstanceTrait;
    use CreateAppInstanceTrait;
    use DeployAppInstanceTrait;
    use PollDeployProgressAppInstanceTrait;
    use PollHealthProgressAppInstanceTrait;
    use PollUpgradeProgressAppInstanceTrait;
    use PostCreateAppInstanceTrait;
    use PostDeployAppInstanceTrait;
    use PostRemoveAppInstanceTrait;
    use PostUpgradeAppInstanceTrait;
    use PreCreateAppInstanceTrait;
    use PreDeployAppInstanceTrait;
    use PreRemoveAppInstanceTrait;
    use PreUpgradeAppInstanceTrait;
    use RemoveAppInstanceTrait;
    use UpgradeAppInstanceTrait;
    use UsesAmazeeAiBackend;

    public static string $version = '0.0.1';

    protected LagoonClient $lagoonClient;

    protected PolydockEngineInterface $engine;

    protected PolydockServiceProviderInterface $lagoonClientProvider;

    protected AmazeeAiBackendClient $amazeeAiBackendClient;

    private PolydockServiceProviderInterface $amazeeAiBackendClientProvider;

    /**
     * @return array<PolydockAppVariableDefinitionInterface>
     */
    public static function getAppDefaultVariableDefinitions(): array
    {
        return [
            new PolydockAppVariableDefinitionBase('lagoon-deploy-git'),
            new PolydockAppVariableDefinitionBase('lagoon-deploy-branch'),
            new PolydockAppVariableDefinitionBase('lagoon-deploy-region-id'),
            new PolydockAppVariableDefinitionBase('lagoon-deploy-private-key'),
            new PolydockAppVariableDefinitionBase('lagoon-deploy-organization-id'),
            new PolydockAppVariableDefinitionBase('lagoon-deploy-project-prefix'),
            new PolydockAppVariableDefinitionBase('lagoon-project-name'),
            new PolydockAppVariableDefinitionBase('lagoon-deploy-group-name'),
        ];
    }

    public static function getAppVersion(): string
    {
        return self::$version;
    }

    public static function getStoreAppFormSchema(): array
    {
        return [];
    }

    public static function getStoreAppInfolistSchema(): array
    {
        return [];
    }

    #[\Override]
    public static function getAppInstanceFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('openclaw_default_model')
                ->label('openClawDefaultModel')
                ->placeholder('e.g. anthropic/claude-3-5-sonnet')
                ->maxLength(255)
                ->helperText('Default model for OpenClaw MAZ behavior.'),
        ];
    }

    #[\Override]
    public static function getAppInstanceInfolistSchema(): array
    {
        return [
            Infolists\Components\TextEntry::make('openclaw_default_model')
                ->label('openClawDefaultModel')
                ->placeholder('Not configured'),
        ];
    }

    /**
     * @throws PolydockAppInstanceStatusFlowException
     */
    public function pingLagoonAPI(): bool
    {
        if (! $this->lagoonClient) {
            throw new PolydockAppInstanceStatusFlowException('Lagoon client not found for ping');
        }

        try {
            $ping = $this->lagoonClient->pingLagoonAPI();

            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon API ping', ['ping' => $ping]);
            }

            return $ping;
        } catch (\Exception $e) {
            throw new PolydockAppInstanceStatusFlowException('Error pinging Lagoon API: '.$e->getMessage());
        }
    }

    /**
     * @throws PolydockAppInstanceStatusFlowException
     */
    public function setLagoonClientFromAppInstance(PolydockAppInstanceInterface $appInstance): void
    {
        $engine = $appInstance->getEngine();
        $this->engine = $engine;

        $lagoonClientProvider = $engine->getPolydockServiceProviderSingletonInstance('PolydockServiceProviderFTLagoon');
        $this->lagoonClientProvider = $lagoonClientProvider;

        if (! method_exists($lagoonClientProvider, 'getLagoonClient')) {
            throw new PolydockAppInstanceStatusFlowException('Lagoon client provider does not have getLagoonClient method');
        }

        /** @phpstan-ignore-next-line */
        $this->lagoonClient = $this->lagoonClientProvider->getLagoonClient();

        if (! $this->lagoonClient) {
            throw new PolydockAppInstanceStatusFlowException('Lagoon client not found');
        }

        if (! ($this->lagoonClient instanceof LagoonClient)) {
            throw new PolydockAppInstanceStatusFlowException('Lagoon client is not an instance of LagoonClient');
        }
    }

    public function verifyLagoonValuesAreAvailable(PolydockAppInstanceInterface $appInstance, array $logContext = []): bool
    {
        $lagoonDeployGit = $appInstance->getKeyValue('lagoon-deploy-git');
        $lagoonRegionId = $appInstance->getKeyValue('lagoon-deploy-region-id');
        $lagoonPrivateKey = $appInstance->getKeyValue('lagoon-deploy-private-key');
        $lagoonOrganizationId = $appInstance->getKeyValue('lagoon-deploy-organization-id');
        $lagoonGroupName = $appInstance->getKeyValue('lagoon-deploy-group-name');
        $lagoonProjectPrefix = $appInstance->getKeyValue('lagoon-deploy-project-prefix');
        $lagoonProjectName = $appInstance->getKeyValue('lagoon-project-name');
        $lagoonAppInstanceHealthWebhookUrl = $appInstance->getKeyValue('polydock-app-instance-health-webhook-url');
        $appType = $appInstance->getAppType();

        if (! $lagoonDeployGit) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon deploy git value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonRegionId) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon region id value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonPrivateKey) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon private key value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonOrganizationId) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon organization id value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonGroupName) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon group name value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonProjectPrefix) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon project prefix value not set', $logContext);
            }

            return false;
        }

        if (! $appType) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('App type value not set, and Polydock needs this to be set in Lagoon', $logContext);
            }

            return false;
        }

        if (! $lagoonProjectName) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon project name value not set', $logContext);
            }

            return false;
        }

        if (! $lagoonAppInstanceHealthWebhookUrl) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon app instance health webhook url value not set', $logContext);
            }

            return false;
        }

        return true;
    }

    public function verifyLagoonProjectNameIsAvailable(PolydockAppInstanceInterface $appInstance, array $logContext = []): bool
    {
        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        if (! $projectName) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon project name not available', $logContext);
            }

            return false;
        }

        return true;
    }

    public function verifyLagoonProjectIdIsAvailable(PolydockAppInstanceInterface $appInstance, array $logContext = []): bool
    {
        $projectId = $appInstance->getKeyValue('lagoon-project-id');
        if (! $projectId) {
            if ($this->lagoonClient->getDebug()) {
                $this->debug('Lagoon project id not available', $logContext);
            }

            return false;
        }

        return true;
    }

    public function verifyLagoonProjectAndIdAreAvailable(PolydockAppInstanceInterface $appInstance, array $logContext = []): bool
    {
        if (! $this->verifyLagoonProjectNameIsAvailable($appInstance, $logContext)) {
            return false;
        }

        if (! $this->verifyLagoonProjectIdIsAvailable($appInstance, $logContext)) {
            return false;
        }

        return true;
    }

    /**
     * @throws PolydockAppInstanceStatusFlowException
     */
    public function validateLagoonPingAndThrowExceptionIfFailed(array $logContext = []): void
    {
        $ping = $this->pingLagoonAPI();
        if (! $ping) {
            $this->error('Lagoon API ping failed', $logContext);
            throw new PolydockAppInstanceStatusFlowException('Lagoon API ping failed');
        }
    }

    /**
     * @throws PolydockAppInstanceStatusFlowException
     */
    public function validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
        PolydockAppInstanceInterface $appInstance,
        PolydockAppInstanceStatus $expectedStatus,
        $logContext = [],
        bool $testLagoonPing = true,
        bool $verifyLagoonValuesAreAvailable = true,
        bool $verifyLagoonProjectNameIsAvailable = true,
        bool $verifyLagoonProjectIdIsAvailable = true
    ): void {
        $this->validateAppInstanceStatusIsExpected($appInstance, $expectedStatus);
        $this->setLagoonClientFromAppInstance($appInstance);

        if ($testLagoonPing) {
            $this->validateLagoonPingAndThrowExceptionIfFailed((array) $appInstance);
            $this->info('Lagoon API ping successful', $logContext);
        }

        if ($verifyLagoonValuesAreAvailable) {
            if (! $this->verifyLagoonValuesAreAvailable($appInstance, $logContext)) {
                $this->error('Required Lagoon values not available', $logContext);
                throw new PolydockAppInstanceStatusFlowException('Required Lagoon values not available');
            }
        }

        if ($verifyLagoonProjectNameIsAvailable) {
            if (! $this->verifyLagoonProjectNameIsAvailable($appInstance, $logContext)) {
                $this->error('Lagoon project name not available', $logContext);
                throw new PolydockAppInstanceStatusFlowException('Lagoon project name not available');
            }
        }

        if ($verifyLagoonProjectIdIsAvailable) {
            if (! $this->verifyLagoonProjectIdIsAvailable($appInstance, $logContext)) {
                $this->error('Lagoon project id not available', $logContext);
                throw new PolydockAppInstanceStatusFlowException('Lagoon project id not available');
            }
        }
    }

    public function getLogContext(string $location): array
    {
        return ['class' => self::class, 'location' => $location];
    }

    /**
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function addOrUpdateLagoonProjectVariable(PolydockAppInstanceInterface $appInstance, $variableName, $variableValue, $variableScope): void
    {
        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $projectId = $appInstance->getKeyValue('lagoon-project-id');
        $logContext = $this->getLogContext('addOrUpdateLagoonProjectVariable');
        $logContext['projectName'] = $projectName;
        $logContext['projectId'] = $projectId;
        $logContext['variableName'] = $variableName;
        $logContext['variableValue'] = $variableValue;
        $logContext['variableScope'] = $variableScope;

        $variable = $this->lagoonClient->addOrUpdateScopedVariableForProject($projectName, $variableName, $variableValue, $variableScope);

        if (isset($variable['error'])) {
            $this->error('Failed to add or update '.$variableName.' variable',
                $logContext + [
                    'lagoonVariable' => $variable,
                    'error' => $variable['error'],
                ]);
            throw new \Exception('Failed to add or update '.$variableName.' variable');
        }

        if ($this->lagoonClient->getDebug()) {
            $this->debug('Added or updated variable', $logContext);
        }
    }

}
