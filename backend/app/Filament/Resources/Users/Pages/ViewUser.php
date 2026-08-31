<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserStatusEnum;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('activate')
                ->label('Activate')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn() => $this->record->status === UserStatusEnum::INACTIVE)
                ->action(fn() => app(UserService::class)->activateUser($this->record)),
            Action::make('deactivate')
                ->label('Deactivate')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn() => $this->record->status === UserStatusEnum::ACTIVE && $this->record->id !== auth()->id())
                ->action(fn() => app(UserService::class)->deactivateUser($this->record)),
        ];
    }
}
