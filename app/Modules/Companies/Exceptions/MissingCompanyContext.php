<?php

namespace App\Modules\Companies\Exceptions;

use LogicException;

class MissingCompanyContext extends LogicException
{
    public function __construct()
    {
        parent::__construct('Aucune société courante : les données métier ne peuvent pas être lues ni écrites hors du contexte d’une société.');
    }
}
