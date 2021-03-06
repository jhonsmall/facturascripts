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
namespace FacturaScripts\Core\Base\ExtendedController;

use FacturaScripts\Core\Model as Model;

/**
 * Definición base para vistas de uso en ExtendedControllers
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 * @author Artex Trading sa <jcuello@artextrading.com>
 */
abstract class BaseView
{

    /**
     * Modelo con los datos a mostrar
     * o necesario para llamadas a los métodos del modelo
     *
     * @var mixed
     */
    protected $model;

    /**
     * Configuración de columnas y filtros
     *
     * @var Model\PageOption
     */
    protected $pageOption;

    /**
     * Indicativo del tipo de vista
     *
     * @var string
     */
    public $viewType;

    /**
     * Título identificativo de la vista
     *
     * @var string
     */
    public $title;

    /**
     * Método para la exportación de los datos de la vista
     * 
     * @param Base\ExportManager $exportManager
     * @param string $action
     */
    abstract public function export(&$exportManager, $action);
    
    /**
     * Constructor e inicializador de la clase
     *
     * @param string $title
     * @param string $modelName
     * @param string $viewName
     * @param string $userNick
     */
    public function __construct($title, $modelName, $viewName, $userNick)
    {
        $this->viewType = 'base';
        $this->title = $title;
        $this->model = empty($modelName) ? NULL : new $modelName;

        // Carga configuración de la vista para el usuario
        $this->pageOption = new Model\PageOption();
        $this->pageOption->getForUser($viewName, $userNick);
    }

    /**
     * Si existe, devuelve el tipo de row especificado
     *
     * @param string $key
     *
     * @return RowItem
     */
    public function getRow($key)
    {
        return empty($this->pageOption->rows) ? NULL : $this->pageOption->rows[$key];
    }

    /**
     * Devuelve la url del modelo del tipo solicitado
     *
     * @param string $type      (edit / list / auto)
     * @return string
     */
    public function getURL($type)
    {
        return empty($this->model) ? '' : $this->model->url($type);
    }

    /**
     * Devuelve el identificador del modelo
     *
     * @return string
     */
    public function getModelID()
    {
        return empty($this->model) ? '' : $this->model->modelClassName();
    }    
}
