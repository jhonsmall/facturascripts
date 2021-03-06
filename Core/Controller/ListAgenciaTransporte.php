<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2015       Pablo Peralta
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

namespace FacturaScripts\Core\Controller;

use FacturaScripts\Core\Base\ExtendedController;

/**
 * Controlador para la lista de agencias de transportes
 *
 * @author Pablo Peralta
 * @author Carlos García Gómez <carlos@facturascripts.com>
 * @author Artex Trading sa <jcuello@artextrading.com>
 */
class ListAgenciaTransporte extends ExtendedController\ListController
{
    public function getPageData()
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'Transportistas';
        $pagedata['icon'] = 'fa-truck';
        $pagedata['menu'] = 'admin';

        return $pagedata;
    }

    protected function createViews()
    {
        $className = $this->getClassName();
        $this->addView('FacturaScripts\Core\Model\AgenciaTransporte', $className);
        $this->addSearchFields($className, ['nombre', 'codtrans']);

        $this->addOrderBy($className, 'codtrans', 'Código');
        $this->addOrderBy($className, 'nombre');

        $this->addFilterCheckbox($className, 'activo', 'Activo', '', TRUE);
    }
}
