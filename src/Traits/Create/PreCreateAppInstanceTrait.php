<?php

declare(strict_types=1);

namespace Amazeeio\PolydockAppAmazeeclaw\Traits\Create;

use FreedomtechHosting\PolydockApp\Enums\PolydockAppInstanceStatus;
use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;

trait PreCreateAppInstanceTrait
{
    public function preCreateAppInstance(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $functionName = __FUNCTION__;
        $logContext = $this->getLogContext($functionName);
        $testLagoonPing = true;
        $validateLagoonValues = true;
        $validateLagoonProjectName = true;
        $validateLagoonProjectId = false;

        $this->info("{$functionName}: starting", $logContext);

        $this->validateAppInstanceStatusIsExpectedAndConfigureLagoonClientAndVerifyLagoonValues(
            $appInstance,
            PolydockAppInstanceStatus::PENDING_PRE_CREATE,
            $logContext,
            $testLagoonPing,
            $validateLagoonValues,
            $validateLagoonProjectName,
            $validateLagoonProjectId
        );

        $projectName = $appInstance->getKeyValue('lagoon-project-name');
        $projectPrefix = $appInstance->getKeyValue('lagoon-deploy-project-prefix');
        if ($projectPrefix !== '') {
            $projectName = $this->generateUniqueProjectName($projectPrefix);
            /** @phpstan-ignore-next-line */
            $appInstance->setName($projectName);
            $appInstance->storeKeyValue('lagoon-project-name', $projectName);
            $appInstance->save();
        }

        $this->info("{$functionName}: starting for project: {$projectName}", $logContext);
        $appInstance->setStatus(
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING,
            PolydockAppInstanceStatus::PRE_CREATE_RUNNING->getStatusMessage()
        )->save();

        $this->info("{$functionName}: completed", $logContext);
        $appInstance->setStatus(PolydockAppInstanceStatus::PRE_CREATE_COMPLETED, 'Pre-create completed')->save();

        return $appInstance;
    }

    /**
     * Local override point for project-name strategy.
     *
     * Tweak this method directly if you want a different order/length,
     * e.g. animal before color, or a shorter unique id.
     */
    protected function generateUniqueProjectName(string $prefix): string
    {
        $uniqueIdLengthBytes = 3; // 6 hex chars
        try {
            $shortUniqueId = bin2hex(random_bytes($uniqueIdLengthBytes));
        } catch (\Exception) {
            // Fallback preserves randomness if secure source is unavailable.
            $shortUniqueId = substr(hash('sha256', uniqid('', true)), 0, $uniqueIdLengthBytes * 2);
        }

        return strtolower(
            "{$prefix}-{$this->pickAdjective()}-{$this->pickAnimal()}-{$shortUniqueId}"
        );
    }

    protected function pickAnimal(): string
    {
        $animals = [
            'crab', 'lobster', 'crayfish', 'prawn', 'shrimp',
            'hermitcrab', 'fiddlercrab', 'kingcrab', 'rocklobster', 'langoustine',
            'scorpion', 'mantis',
        ];

        return $animals[array_rand($animals)];
    }

    protected function pickAdjective(): string
    {
        $adjectives = [
            'snappy', 'pinchy', 'crabby', 'clawesome', 'nippy',
            'cheeky', 'zesty', 'scrappy', 'wiggly', 'spiky',
            'grumpy', 'sassy', 'bouncy', 'sneaky', 'jolly',
        ];

        return $adjectives[array_rand($adjectives)];
    }
}
