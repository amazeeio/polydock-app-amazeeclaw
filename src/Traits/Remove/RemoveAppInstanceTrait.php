<?php

namespace Amazeeio\PolydockAppAmazeeclaw\Traits\Remove;

use FreedomtechHosting\FtLagoonPhp\LagoonClientInitializeRequiredToInteractException;
use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;

trait RemoveAppInstanceTrait
{
    /**
     * @throws PolydockAppInstanceStatusFlowException
     * @throws LagoonClientInitializeRequiredToInteractException
     */
    public function removeAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
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
            PolydockAppInstanceStatus::PENDING_REMOVE,
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

        $this->info($functionName.': starting for project: '.$projectName, $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::REMOVE_RUNNING,
            PolydockAppInstanceStatus::REMOVE_RUNNING->getStatusMessage()
        )->save();

        $removedEnvironment = $this->lagoonClient->deleteProjectEnvironmentByName(
            $projectName,
            $deployEnvironment
        );

        if (isset($removedEnvironment['error'])) {
            $errorMessage = is_array($removedEnvironment['error'])
                ? ($removedEnvironment['error'][0]['message'] ?? json_encode($removedEnvironment['error']))
                : $removedEnvironment['error'];
            $this->error($errorMessage);
            $appInstance->setStatus(PolydockAppInstanceStatus::REMOVE_FAILED, 'Failed to remove Lagoon environment', $logContext + ['error' => $removedEnvironment['error']])->save();

            return $appInstance;
        }

        if (isset($removedEnvironment['deleteEnvironment']) && $removedEnvironment['deleteEnvironment'] === 'success') {
            $this->info('Environment deleted successfully', $logContext + ['removedEnvironment' => $removedEnvironment]);
        } else {
            $appInstance->setStatus(PolydockAppInstanceStatus::REMOVE_FAILED, 'No error, but failed to remove Lagoon environment', $logContext + ['error' => $removedEnvironment['error']])->save();

            return $appInstance;
        }

        $this->info($functionName.': completed', $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::REMOVE_COMPLETED, 'Remove completed')->save();

        return $appInstance;
    }
}
