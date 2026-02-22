<?php

namespace Amazeeio\PolydockAppAmazeeclaw\Traits\Upgrade;

use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;

trait PreUpgradeAppInstanceTrait
{
    public function preUpgradeAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
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
            PolydockAppInstanceStatus::PENDING_PRE_UPGRADE,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $projectId = $appInstance->getKeyValue('lagoon-project-id');

        $this->info($functionName.': starting for project: '.$projectName.' ('.$projectId.')', $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::PRE_UPGRADE_RUNNING,
            PolydockAppInstanceStatus::PRE_UPGRADE_RUNNING->getStatusMessage()
        )->save();

        $this->info($functionName.': completed', $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::PRE_UPGRADE_COMPLETED, 'Pre-upgrade completed')->save();

        return $appInstance;
    }
}
