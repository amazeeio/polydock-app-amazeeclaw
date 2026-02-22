<?php

declare(strict_types=1);

namespace Amazeeio\PolydockAppAmazeeclaw;

use Amazeeio\PolydockAppAmazeeclaw\Traits\Create\PostCreateAppInstanceTrait;
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
    use PostCreateAppInstanceTrait;
    use UsesAmazeeAiBackend;

    public static string $version = '0.0.1';

    #[\Override]
    public static function getAppInstanceFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('openclaw_default_model')
                ->label('openClawDefaultModel')
                ->placeholder('e.g. anthropic/claude-3-5-sonnet')
                ->maxLength(255)
                ->helperText('Default model for OpenClaw MAZ behavior.'),
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
