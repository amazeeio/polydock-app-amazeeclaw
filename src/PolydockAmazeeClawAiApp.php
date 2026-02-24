<?php

declare(strict_types=1);

namespace Amazeeio\PolydockAppAmazeeclaw;

use Amazeeio\PolydockAppAmazeeclaw\Traits\Claim\ClaimAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Create\PostCreateAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\Create\PreCreateAppInstanceTrait;
use Amazeeio\PolydockAppAmazeeclaw\Traits\UsesAmazeeAiBackend;
use Filament\Forms;
use Filament\Infolists;
use FreedomtechHosting\PolydockApp\Attributes\PolydockAppInstanceFields;
use FreedomtechHosting\PolydockApp\Attributes\PolydockAppStoreFields;
use FreedomtechHosting\PolydockApp\Attributes\PolydockAppTitle;
use FreedomtechHosting\PolydockApp\Contracts\HasAppInstanceFormFields;
use FreedomtechHosting\PolydockApp\Contracts\HasStoreAppFormFields;
use FreedomtechHosting\PolydockAppAmazeeioGeneric\PolydockAiApp as GenericPolydockAiApp;

#[PolydockAppTitle('AmazeeClaw AI App')]
#[PolydockAppStoreFields]
#[PolydockAppInstanceFields]
class PolydockAmazeeClawAiApp extends GenericPolydockAiApp implements HasAppInstanceFormFields, HasStoreAppFormFields
{
    use ClaimAppInstanceTrait;
    use PostCreateAppInstanceTrait;
    use PreCreateAppInstanceTrait;
    use UsesAmazeeAiBackend;

    public static string $version = '0.1.7';

    #[\Override]
    public static function getStoreAppFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('openclaw_default_model')
                ->label('openClawDefaultModel')
                ->placeholder('e.g. kimi-k2.5')
                ->maxLength(255)
                ->helperText('Default model for OpenClaw amazee.ai behavior. Used when instance value is not set.'),
        ];
    }

    #[\Override]
    public static function getStoreAppInfolistSchema(): array
    {
        return [
            Infolists\Components\TextEntry::make('openclaw_default_model')
                ->label('openClawDefaultModel')
                ->placeholder('Not configured'),
        ];
    }

    #[\Override]
    public static function getAppInstanceFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('openclaw_default_model')
                ->label('openClawDefaultModel')
                ->placeholder('e.g. kimi-k2.5')
                ->maxLength(255)
                ->helperText('Optional override for this specific instance.'),
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
}
