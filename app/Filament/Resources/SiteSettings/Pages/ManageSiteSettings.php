<?php

namespace App\Filament\Resources\SiteSettings\Pages;

use App\Filament\Resources\SiteSettings\SiteSettingResource;
use App\Models\SiteSetting;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSiteSettings extends ManageRecords
{
    protected static string $resource = SiteSettingResource::class;

    public function mount(): void
    {
        parent::mount();

        // Guarantee the single content row exists so admins land on an editable record.
        SiteSetting::current();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn () => ! SiteSetting::query()->exists()),
        ];
    }
}
