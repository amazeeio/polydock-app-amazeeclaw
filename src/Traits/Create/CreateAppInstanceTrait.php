<?php

namespace Amazeeio\PolydockAppAmazeeclaw\Traits\Create;

use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;

trait CreateAppInstanceTrait
{
    public function createAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;
        $logContext = $this->getLogContext($functionName);
        $testLagoonPing = true;
        $validateLagoonValues = true;
        $validateLagoonProjectName = true;
        $validateLagoonProjectId = false;

        $this->info($functionName.': starting', $logContext);

        $this->validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_CREATE,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');

        $this->info($functionName.': starting for project: '.$projectName, $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::CREATE_RUNNING,
            PolydockAppInstanceStatus::CREATE_RUNNING->getStatusMessage()
        )->save();

        $addOrgOwnerToProject = true;
        $createdProjectData = $this->lagoonClient->createLagoonProjectInOrganization(
            $projectName,
            $appInstance->getKeyValue('lagoon-deploy-git'),
            $appInstance->getKeyValue('lagoon-deploy-branch'),
            $appInstance->getKeyValue('lagoon-deploy-branch'),
            $appInstance->getKeyValue('lagoon-deploy-region-id'),
            $appInstance->getKeyValue('lagoon-deploy-private-key'),
            $appInstance->getKeyValue('lagoon-deploy-organization-id'),
            $addOrgOwnerToProject
        );

        if (isset($createdProjectData['error'])) {
            $errorMessage = is_array($createdProjectData['error'])
                ? ($createdProjectData['error'][0]['message'] ?? json_encode($createdProjectData['error']))
                : $createdProjectData['error'];
            $this->error($errorMessage, $logContext);
            $appInstance->setStatus(PolydockAppInstanceStatus::CREATE_FAILED, 'Failed to create Lagoon project', $logContext + ['error' => $createdProjectData['error']])->save();

            return $appInstance;
        }

        if (! isset($createdProjectData['addProject']['id'])) {
            $appInstance->setStatus(PolydockAppInstanceStatus::CREATE_FAILED, 'Failed to create Lagoon project', $logContext + ['error' => 'Missing project id'])->save();

            return $appInstance;
        }

        $appInstance->storeKeyValue('lagoon-project-id', $createdProjectData['addProject']['id']);

        $this->info($functionName.': completed', $logContext + ['projectId' => $createdProjectData['addProject']['id']]);
        $appInstance->setStatus(PolydockAppInstanceStatus::CREATE_COMPLETED, 'Create completed')->save();

        return $appInstance;
    }
}
