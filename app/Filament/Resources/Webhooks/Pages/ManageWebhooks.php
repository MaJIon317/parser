<?php

namespace App\Filament\Resources\Webhooks\Pages;

use App\Filament\Resources\Webhooks\WebhookResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWebhooks extends ManageRecords
{
    protected static string $resource = WebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
