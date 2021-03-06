<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  carlos@facturascripts.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Core\Base;

/**
 * Description of NumberTools
 *
 * @author carlos
 */
class NumberTools
{
    const NF1 = ',';
    const NF2 = ' ';

    private static $decimals;

    public function __construct()
    {
        if (!isset(self::$decimals)) {
            self::$decimals = 0;
        }
    }

    public function format($number, $decimals = '')
    {
        if($decimals == '') {
            $decimals = self::$decimals;
        }
        
        return number_format($number, $decimals, self::NF1, self::NF2);
    }
}
