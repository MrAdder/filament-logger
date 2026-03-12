<?php

namespace MrAdder\FilamentLogger\Resources;

use Filament\Forms\Form;
use Filament\Infolists\Infolist;

class ActivityResourceV3 extends BaseActivityResource
{
    public static function infolist(Infolist $infolist): Infolist
    {
        /** @var Infolist $infolist */
        $infolist = static::configureInfolist($infolist);

        return $infolist;
    }

    public static function form(Form $form): Form
    {
        /** @var Form $form */
        $form = static::configureForm($form);

        return $form;
    }
}
