<?php

namespace App\Filament\SuperAdmin\Resources\EmailTemplateResource\Pages;

use App\Filament\SuperAdmin\Resources\EmailTemplateResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditEmailTemplate extends BaseEditRecord
{
    use Translatable;

    protected static string $resource = EmailTemplateResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            \Filament\Actions\LocaleSwitcher::make(),
            Actions\Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading('Email Preview')
                ->modalContent(fn() => view('filament.super-admin.modals.email-template-preview', ['record' => $this->record])),

            Actions\DeleteAction::make(),
        ];
    }
}
