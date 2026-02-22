<?php

namespace Amazeeio\PolydockAppAmazeeclaw\Traits\Upgrade;

use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;

trait UpgradeAppInstanceTrait
{
    public function upgradeAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
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
            PolydockAppInstanceStatus::PENDING_UPGRADE,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');

        $this->info($functionName.': starting for project: '.$projectName, $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::UPGRADE_RUNNING,
            PolydockAppInstanceStatus::UPGRADE_RUNNING->getStatusMessage()
        );

        $appInstance->warning('TODO: Implement upgrade logic', $logContext);
        try {
            $this->addOrUpdateLagoonProjectVariable($appInstance, 'POLYDOCK_APP_NAME', $appInstance->getApp()->getAppName(), 'GLOBAL');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $appInstance->setStatus(PolydockAppInstanceStatus::UPGRADE_FAILED, $e->getMessage())->save();

            return $appInstance;
        }

        $this->info($functionName.': completed', $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::UPGRADE_RUNNING, 'Upgrade completed')->save();

        return $appInstance;
    }
}
