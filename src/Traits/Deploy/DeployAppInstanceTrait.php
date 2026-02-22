<?php

namespace Amazeeio\PolydockAppAmazeeclaw\Traits\Deploy;

use FreedomtechHosting\FtLagoonPhp\LagoonClientInitializeRequiredToInteractException;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;

trait DeployAppInstanceTrait
{
    /**
     * @throws PolydockAppInstanceStatusFlowException
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function deployAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;
        $logContext = $this->getLogContext($functionName);
        $testLagoonPing = true;
        $validateLagoonValues = true;
        $validateLagoonProjectName = true;
        $validateLagoonProjectId = true;

        $this->info($functionName.': starting', $logContext);

        $this->validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_DEPLOY,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $deployEnvironment = $appInstance->getKeyValue('lagoon-deploy-branch');
        $logContext['projectName'] = $projectName;
        $logContext['deployEnvironment'] = $deployEnvironment;

        $this->info($functionName.': starting for project: '.$projectName.' and environment: '.$deployEnvironment, $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::DEPLOY_RUNNING,
            PolydockAppInstanceStatus::DEPLOY_RUNNING->getStatusMessage()
        )->save();

        $createdDeployment = $this->lagoonClient->deployProjectEnvironmentByName(
            $projectName,
            $deployEnvironment
        );

        if (isset($createdDeployment['error'])) {
            $errorMessage = is_array($createdDeployment['error'])
                ? ($createdDeployment['error'][0]['message'] ?? json_encode($createdDeployment['error']))
                : $createdDeployment['error'];
            $this->error($errorMessage, $logContext);
            $appInstance->setStatus(PolydockAppInstanceStatus::DEPLOY_FAILED, 'Failed to create Lagoon project', $logContext + ['error' => $createdDeployment['error']])->save();

            return $appInstance;
        }

        $latestDeploymentName = $createdDeployment['deployEnvironmentBranch'] ?? null;
        if (empty($latestDeploymentName)) {
            $appInstance->setStatus(PolydockAppInstanceStatus::DEPLOY_FAILED, 'Failed to create Lagoon project', $logContext + ['error' => 'Missing deployment name'])->save();

            return $appInstance;
        }

        $appInstance->storeKeyValue('lagoon-latest-deployment-name', $latestDeploymentName);

        $this->info($functionName.': completed', $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::DEPLOY_RUNNING, 'Deploy running')->save();

        return $appInstance;
    }
}
