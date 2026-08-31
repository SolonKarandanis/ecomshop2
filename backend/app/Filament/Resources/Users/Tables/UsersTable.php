<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserStatusEnum;
use App\Models\User;
use App\Services\UserService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(UserStatusEnum $state) => UserStatusEnum::labels()[$state->value])
                    ->color(fn(UserStatusEnum $state) => match($state) {
                        UserStatusEnum::ACTIVE => 'success',
                        UserStatusEnum::INACTIVE => 'danger',
                    }),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(User $record) => $record->status === UserStatusEnum::INACTIVE)
                    ->action(fn(User $record) => app(UserService::class)->activateUser($record)),
                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn(User $record) => $record->status === UserStatusEnum::ACTIVE && $record->id !== auth()->id())
                    ->action(fn(User $record) => app(UserService::class)->deactivateUser($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
