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

namespace FacturaScripts\Core\Model;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\ExtendedController;

/**
 * Configuración visual de las vistas de FacturaScripts,
 * cada PageOption se corresponde con un controlador.
 *
 * @author Artex Trading sa <jcuello@artextrading.com>
 */
class PageOption
{
    use Base\ModelTrait {
        clear as clearTrait;
        loadFromData as loadFromDataTrait;
    }

    public $id;

    /**
     * Nombre de la página (controlador).
     *
     * @var string
     */
    public $name;

    /**
     * Identificador del Usuario.
     *
     * @var string
     */
    public $nick;

    /**
     * Definición para tratamiento especial de filas
     *
     * @var array
     */
    public $rows;

    /**
     * Definición de las columnas. Se denomina columns pero contiene
     * siempre GroupItem, el cual contiene las columnas.
     *
     * @var array
     */
    public $columns;

    /**
     * Definición de filtros personalizados
     *
     * @var array
     */
    public $filters;

    public function tableName()
    {
        return 'fs_pages_options';
    }

    public function primaryColumn()
    {
        return 'id';
    }

    public function install()
    {
        /// necesitamos estas clase para las claves ajenas
        new Page();
        new User();

        return '';
    }

    /**
     * Resetea los valores de todas las propiedades modelo.
     */
    public function clear()
    {
        $this->clearTrait();
        $this->columns = [];
        $this->filters = [];
        $this->rows = [];
    }

    /**
     * Carga los datos desde un array
     *
     * @param array $data
     */
    public function loadFromData($data)
    {
        $this->loadFromDataTrait($data, ['columns', 'filters', 'rows']);

        $groups = json_decode($data['columns'], true);
        foreach ($groups as $item) {
            $groupItem = new ExtendedController\GroupItem();
            $groupItem->loadFromJSON($item);

            $this->columns[$groupItem->name] = $groupItem;
            unset($groupItem);
        }

        $rows = json_decode($data['rows'], true);
        if (!empty($rows)) {
            $rowItem = new ExtendedController\RowItem();
            $rowItem->loadFromJSON($rows);
            $this->rows[] = $rowItem;
        }
    }

    /**
     * Actualiza los datos del modelo en la base de datos.
     *
     * @return bool
     */
    private function saveUpdate()
    {
        $columns = json_encode($this->columns);
        $filters = json_encode($this->filters);
        $rows = json_encode($this->rows);

        $sql = 'UPDATE ' . $this->tableName() . ' SET '
            . '  columns = ' . $this->var2str($columns)
            . ' ,filters = ' . $this->var2str($filters)
            . ' ,rows = ' . $this->var2str($rows)
            . ' WHERE id = ' . $this->id . ';';

        return $this->dataBase->exec($sql);
    }

    /**
     * Inserta los datos del modelo en la base de datos.
     *
     * @return bool
     */
    private function saveInsert()
    {
        $columns = json_encode($this->columns);
        $filters = json_encode($this->filters);
        $rows = json_encode($this->rows);

        $sql = 'INSERT INTO ' . $this->tableName()
            . ' (id, name, nick, columns, filters, rows) VALUES ('
            . "nextval('fs_pages_options_id_seq')" . ','
            . $this->var2str($this->name) . ','
            . $this->var2str($this->nick) . ','
            . $this->var2str($columns) . ','
            . $this->var2str($filters) . ','
            . $this->var2str($rows)
            . ');';

        if ($this->dataBase->exec($sql)) {
            $this->id = $this->dataBase->lastval();

            return true;
        }

        return false;
    }

    /**
     * Carga la estructura de columnas desde el XML
     *
     * @param SimpleXMLElement $columns
     */
    private function getXMLGroupsColumns($columns)
    {
        // No hay agrupación de columnas
        if (empty($columns->group)) {
            $groupItem = new ExtendedController\GroupItem();
            $groupItem->loadFromXMLColumns($columns);
            $this->columns[$groupItem->name] = $groupItem;
            unset($groupItem);

            return;
        }

        // Con agrupación de columnas
        foreach ($columns->group as $group) {
            $groupItem = new ExtendedController\GroupItem();
            $groupItem->loadFromXML($group);
            $this->columns[$groupItem->name] = $groupItem;
            unset($groupItem);
        }
    }

    /**
     * Carga las condiciones especiales para las filas
     * desde el XML
     *
     * @param SimpleXMLElement $rows
     */
    private function getXMLRows($rows)
    {
        if (empty($rows)) {
            return;
        }
        
        foreach ($rows->row as $row) {
            $rowItem = new ExtendedController\RowItem();
            $rowItem->loadFromXML($row);
            $this->rows[$rowItem->type] = $rowItem;
            unset($rowItem);
        }
    }

    /**
     * Añade a la configuración de un controlador
     *
     * @param string $name
     */
    public function installXML($name)
    {
        if ($this->name != $name) {
            $this->miniLog->critical('error-install-name-xmlview');
            return;
        }
        
        $file = "Core/XMLView/{$name}.xml";
        $xml = simplexml_load_file($file);

        if ($xml === false) {
            $this->miniLog->critical('error-processing-xmlview');
            return;
        }

        $this->getXMLGroupsColumns($xml->columns);
        $this->getXMLRows($xml->rows);
    }

    /**
     * Obtiene la configuración para el controlador y usuario
     *
     * @param string $name
     * @param string $nick
     */
    public function getForUser($name, $nick)
    {
        $where = [];
        $where[] = new DataBase\DataBaseWhere('nick', $nick);
        $where[] = new DataBase\DataBaseWhere('nick', 'NULL', 'IS', 'OR');
        $where[] = new DataBase\DataBaseWhere('name', $name);

        $orderby = ['nick' => 'ASC'];

        // Load data from database, if not exist install xmlview
        if (!$this->loadFromCode('', $where, $orderby)) {
            $this->name = $name;
            $this->columns = [];
            $this->filters = [];
            $this->rows = [];
            $this->installXML($name);
        }

        // Aplicamos sobre los widgets Select enlazados a base de datos los valores de los registros.
        $this->searchSelectValues();
    }

    /**
     * Obtiene la columna para el nombre de campo informado
     *
     * @param string $fieldName
     *
     * @return ColumnItem
     */
    public function columnForField($fieldName)
    {
        $result = NULL;
        foreach ($this->columns as $group) {
            foreach ($group->columns as $column) {
                if ($column->widget->fieldName === $fieldName) {
                    $result = $column;
                    break;
                }
            }
            if (!empty($result)) {
                break;
            }
        }

        return $result;
    }

    /**
     * Carga la lista de valores para un widget de tipo select relacionado
     * con un modelo de la base de datos
     */
    private function searchSelectValues()
    {
        foreach ($this->columns as $group) {
            foreach ($group->columns as $column) {
                if (($column->widget->type === 'select') && array_key_exists('source', $column->widget->values[0])) {
                    $tableName = $column->widget->values[0]['source'];
                    $fieldCode = $column->widget->values[0]['fieldcode'];
                    $fieldDesc = $column->widget->values[0]['fieldtitle'];
                    $allowEmpty = !$column->widget->required;
                    $rows = CodeModel::all($tableName, $fieldCode, $fieldDesc, $allowEmpty);
                    $column->widget->setValuesFromCodeModel($rows);
                    unset($rows);
                }
            }
        }
    }
}
