<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserStatusEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn(UserStatusEnum $state) => UserStatusEnum::labels()[$state->value])
                    ->color(fn(UserStatusEnum $state) => match($state) {
                        UserStatusEnum::ACTIVE => 'success',
                        UserStatusEnum::INACTIVE => 'danger',
                    }),
                TextEntry::make('email_verified_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
