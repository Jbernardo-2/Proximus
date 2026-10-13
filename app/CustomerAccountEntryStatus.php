<?php

namespace App;

enum CustomerAccountEntryStatus: string
{
    case Active = 'active';
    case Reversed = 'reversed';
}
