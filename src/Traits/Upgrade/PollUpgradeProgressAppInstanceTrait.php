<?php

namespace Amazeeio\PolydockAppAmazeeclaw\Traits\Upgrade;

use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;

trait PollUpgradeProgressAppInstanceTrait
{
    public function pollAppInstanceUpgradeProgress(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $logContext = $this->getLogContext(__FUNCTION__);
        $appInstance->warning('TODO: Implement upgrade progress logic', $logContext);

        return $appInstance;
    }
}
