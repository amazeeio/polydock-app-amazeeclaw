<?php

namespace Amazeeio\PolydockAppAmazeeclaw\Traits\Deploy;

use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceStatusFlowException;

trait PostDeployAppInstanceTrait
{
    public function postDeployAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
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
            PolydockAppInstanceStatus::PENDING_POST_DEPLOY,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $deployEnvironment = $appInstance->getKeyValue('lagoon-deploy-branch');
        $logContext = $logContext + ['projectName' => $projectName, 'deployEnvironment' => $deployEnvironment];

        $this->info($functionName.': starting for project: '.$projectName, $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::POST_DEPLOY_RUNNING,
            PolydockAppInstanceStatus::POST_DEPLOY_RUNNING->getStatusMessage()
        )->save();

        $postDeployScript = $appInstance->getKeyValue('lagoon-post-deploy-script');
        $postDeployScriptService = $appInstance->getKeyValue('lagoon-post-deploy-script-service') ?? 'cli';
        $postDeployScriptContainer = $appInstance->getKeyValue('lagoon-post-deploy-script-container') ?? 'cli';
        $logContext = $logContext + ['postDeployScript' => $postDeployScript, 'postDeployScriptService' => $postDeployScriptService, 'postDeployScriptContainer' => $postDeployScriptContainer];

        if (! empty($postDeployScript)) {
            $this->info('Post-deploy script', $logContext);

            try {
                $trialResult = $this->lagoonClient->executeCommandOnProjectEnvironment(
                    $projectName,
                    $deployEnvironment,
                    $postDeployScript,
                    $postDeployScriptService,
                    $postDeployScriptContainer
                );

                $this->info('Trial result', $logContext + ['trialResult' => $trialResult]);

                if ($trialResult['result'] !== 0) {
                    throw new \Exception($trialResult['result'].' | '.$trialResult['result_text'].' | '.$trialResult['error']);
                }
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                $appInstance->setStatus(PolydockAppInstanceStatus::POST_DEPLOY_FAILED, substr($e->getMessage(), 0, 100))->save();

                return $appInstance;
            }
        } else {
            $this->info('No post-deploy script detected', $logContext);
        }

        $this->info($functionName.': completed', $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::POST_DEPLOY_COMPLETED, 'Post-deploy completed')->save();

        return $appInstance;
    }
}
