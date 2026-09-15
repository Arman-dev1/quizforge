<?php

namespace App\Filament\Resources\PaymentSettings\Pages;

use App\Filament\Resources\PaymentSettings\PaymentSettingResource;
use App\Models\PaymentSetting;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentSettings extends ManageRecords
{
    protected static string $resource = PaymentSettingResource::class;

    public function mount(): void
    {
        parent::mount();

        // Land on an editable row rather than an empty table.
        PaymentSetting::current();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
