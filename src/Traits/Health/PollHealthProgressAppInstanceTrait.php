<?php

namespace Amazeeio\PolydockAppAmazeeclaw\Traits\Health;

use FreedomtechHosting\PolydockApp\PolydockAppInstanceInterface;

trait PollHealthProgressAppInstanceTrait
{
    public function pollAppInstanceHealthStatus(PolydockAppInstanceInterface $appInstance): PolydockAppInstanceInterface
    {
        $logContext = $this->getLogContext(__FUNCTION__);
        $appInstance->warning('TODO: Implement health check logic', $logContext);

        return $appInstance;
    }
}
