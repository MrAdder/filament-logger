<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Schemas\Schema;

class ActivityResourceV4 extends BaseActivityResource
{
    public static function infolist(Schema $infolist): Schema
    {
        /** @var Schema $infolist */
        $infolist = static::configureInfolist($infolist);

        return $infolist;
    }

    public static function form(Schema $form): Schema
    {
        /** @var Schema $form */
        $form = static::configureForm($form);

        return $form;
    }
}
